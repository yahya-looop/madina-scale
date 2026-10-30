<?php

use App\Models\InventoryStock;
use App\Models\B2BAccount;
use App\Models\Customer;
use App\Models\InventoryReservationPurpose;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\InventoryTransfer;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\InventoryTransferService;
use App\Services\B2BCreditService;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

function concurrencyUser(array $permissionSlugs): User
{
    $user = User::factory()->create();
    $module = Module::firstOrCreate(['slug' => 'inventory'], [
        'name' => 'Inventory', 'description' => 'Inventory management.',
        'sort_order' => 236, 'is_system' => true, 'is_active' => true,
    ]);
    $role = Role::create([
        'name' => 'Concurrency Test Role', 'slug' => 'concurrency-'.Str::uuid(),
        'description' => 'Temporary role for process concurrency tests.',
        'is_system' => false, 'is_active' => true,
    ]);
    foreach ($permissionSlugs as $slug) {
        $permission = Permission::firstOrCreate(['slug' => $slug], [
            'module_id' => $module->id, 'name' => $slug,
            'description' => 'Concurrency test permission.',
        ]);
        $role->permissions()->attach($permission->id);
    }
    $user->roles()->attach($role->id);

    return $user;
}

/** @return array{0: Process, 1: Process, 2: string} */
function startConcurrentWorkers(array $first, array $second): array
{
    $barrier = sys_get_temp_dir().DIRECTORY_SEPARATOR.'madina-concurrency-'.Str::uuid().'.go';
    $firstToken = (string) Str::uuid();
    $secondToken = (string) Str::uuid();
    $connection = config('database.connections.pgsql');
    $env = [
        'APP_ENV' => 'testing',
        'DB_CONNECTION' => 'pgsql',
        'DB_HOST' => (string) ($connection['host'] ?? '127.0.0.1'),
        'DB_PORT' => (string) ($connection['port'] ?? '5432'),
        'DB_DATABASE' => (string) ($connection['database'] ?? 'madina_scale_test'),
        'DB_USERNAME' => (string) ($connection['username'] ?? ''),
        'DB_PASSWORD' => (string) ($connection['password'] ?? ''),
        'DB_URL' => '',
    ];
    $script = base_path('tests/Concurrency/worker.php');
    $first['barrier'] = $barrier;
    $first['worker_token'] = $firstToken;
    $second['barrier'] = $barrier;
    $second['worker_token'] = $secondToken;

    $one = new Process([PHP_BINARY, $script, base64_encode(json_encode($first, JSON_THROW_ON_ERROR))], base_path(), $env, null, 35);
    $two = new Process([PHP_BINARY, $script, base64_encode(json_encode($second, JSON_THROW_ON_ERROR))], base_path(), $env, null, 35);
    $one->start();
    $two->start();

    $readyFiles = [$barrier.'.'.$firstToken.'.ready', $barrier.'.'.$secondToken.'.ready'];
    $deadline = microtime(true) + 20;
    try {
        // Release only after both workers have bootstrapped Laravel and reached the barrier.
        while (! is_file($readyFiles[0]) || ! is_file($readyFiles[1])) {
            if (microtime(true) >= $deadline) {
                $details = sprintf(
                    'Timed out waiting for workers to become ready. Worker 1 running=%s, exit=%s, stderr=%s; Worker 2 running=%s, exit=%s, stderr=%s',
                    $one->isRunning() ? 'yes' : 'no', var_export($one->getExitCode(), true), $one->getErrorOutput(),
                    $two->isRunning() ? 'yes' : 'no', var_export($two->getExitCode(), true), $two->getErrorOutput(),
                );
                throw new RuntimeException($details);
            }

            // Fail early when a child process exits before reporting readiness.
            if ((! $one->isRunning() && ! is_file($readyFiles[0])) || (! $two->isRunning() && ! is_file($readyFiles[1]))) {
                throw new RuntimeException(sprintf(
                    'A concurrency worker exited before reaching the barrier. Worker 1 exit=%s, stdout=%s, stderr=%s; Worker 2 exit=%s, stdout=%s, stderr=%s',
                    var_export($one->getExitCode(), true), $one->getOutput(), $one->getErrorOutput(),
                    var_export($two->getExitCode(), true), $two->getOutput(), $two->getErrorOutput(),
                ));
            }
            usleep(10_000);
        }

        file_put_contents($barrier, 'go', LOCK_EX);
        $one->wait();
        $two->wait();
    } catch (Throwable $exception) {
        if ($one->isRunning()) {
            $one->stop(1);
        }
        if ($two->isRunning()) {
            $two->stop(1);
        }
        throw $exception;
    } finally {
        @unlink($barrier);
        foreach ($readyFiles as $readyFile) {
            @unlink($readyFile);
        }
    }

    return [$one, $two, $barrier];
}

function assertConcurrencyWorkerCompleted(Process $process): array
{
    expect($process->isSuccessful() || $process->getExitCode() === 2)->toBeTrue(
        'Worker failed unexpectedly. STDOUT: '.$process->getOutput().' STDERR: '.$process->getErrorOutput()
    );
    $line = trim($process->getOutput());
    $result = json_decode($line, true);
    expect($result)->toBeArray('Worker did not return JSON: '.$line.' '.$process->getErrorOutput());

    return $result;
}

test('simultaneous dispatch requests with one idempotency key create one transfer and one stock movement', function () {
    $sender = concurrencyUser(['system.inventory.transfer', 'system.inventory.view']);
    $receiver = concurrencyUser(['system.inventory.transfer', 'system.inventory.view']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();
    $sender->locations()->attach($source->id, ['access_level' => 'manage']);
    $receiver->locations()->attach($destination->id, ['access_level' => 'receive']);
    InventoryStock::query()->create([
        'product_id' => $product->id, 'location_id' => $source->id,
        'quantity' => '20.0000', 'reserved_quantity' => '0.0000', 'low_stock_threshold' => '0',
    ]);
    $key = (string) Str::uuid();
    $data = [
        'product_id' => $product->id, 'source_location_id' => $source->id,
        'destination_location_id' => $destination->id, 'quantity' => '5',
        'idempotency_key' => $key, 'reason' => 'Concurrent idempotency test',
    ];
    [$one, $two] = startConcurrentWorkers(
        ['operation' => 'dispatch', 'actor_id' => $sender->id, 'data' => $data],
        ['operation' => 'dispatch', 'actor_id' => $sender->id, 'data' => $data],
    );
    $oneResult = assertConcurrencyWorkerCompleted($one);
    $twoResult = assertConcurrencyWorkerCompleted($two);

    expect($oneResult['ok'])->toBeTrue();
    expect($twoResult['ok'])->toBeTrue();
    expect(InventoryTransfer::query()->where('idempotency_key', $key)->count())->toBe(1);
    expect((string) InventoryStock::query()->where('product_id', $product->id)->where('location_id', $source->id)->value('quantity'))->toBe('15.0000');
    expect(DB::table('stock_movements')->where('idempotency_key', $key)->count())->toBe(1);
});

test('simultaneous full receipt credits destination stock only once', function () {
    $sender = concurrencyUser(['system.inventory.transfer', 'system.inventory.view']);
    $receiverOne = concurrencyUser(['system.inventory.transfer', 'system.inventory.view']);
    $receiverTwo = concurrencyUser(['system.inventory.transfer', 'system.inventory.view']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();
    $sender->locations()->attach($source->id, ['access_level' => 'manage']);
    $receiverOne->locations()->attach($destination->id, ['access_level' => 'receive']);
    $receiverTwo->locations()->attach($destination->id, ['access_level' => 'receive']);
    InventoryStock::query()->create([
        'product_id' => $product->id, 'location_id' => $source->id,
        'quantity' => '20.0000', 'reserved_quantity' => '0.0000', 'low_stock_threshold' => '0',
    ]);
    app(InventoryTransferService::class)->dispatch([
        'product_id' => $product->id, 'source_location_id' => $source->id,
        'destination_location_id' => $destination->id, 'quantity' => '5',
    ], $sender);
    $transfer = InventoryTransfer::query()->firstOrFail();
    // A receipt must carry a UUID idempotency key. Use distinct keys here
    // so the workers race to receive the same transfer, rather than testing
    // the separate idempotent-replay path.
    $payload = ['operation' => 'receive', 'transfer_id' => $transfer->id];
    [$one, $two] = startConcurrentWorkers(
        $payload + ['actor_id' => $receiverOne->id, 'data' => ['quantity_received' => '5', 'idempotency_key' => (string) Str::uuid()]],
        $payload + ['actor_id' => $receiverTwo->id, 'data' => ['quantity_received' => '5', 'idempotency_key' => (string) Str::uuid()]],
    );
    $results = [assertConcurrencyWorkerCompleted($one), assertConcurrencyWorkerCompleted($two)];

    expect(collect($results)->where('ok', true)->count())->toBe(1);
    expect(collect($results)->where('ok', false)->count())->toBe(1);
    expect((string) InventoryStock::query()->where('product_id', $product->id)->where('location_id', $destination->id)->value('quantity'))->toBe('5.0000');
    expect(DB::table('stock_movements')->where('transfer_group_id', $transfer->transfer_uuid)->where('movement_type', 'transfer_in')->count())->toBe(1);
});

test('simultaneous issue and reservation cannot oversell the same available stock', function () {
    $actor = concurrencyUser(['system.inventory.view']);
    $product = inventoryTestProduct();
    $location = inventoryTestLocation();
    InventoryStock::query()->create([
        'product_id' => $product->id, 'location_id' => $location->id,
        'quantity' => '10.0000', 'reserved_quantity' => '0.0000', 'low_stock_threshold' => '0',
    ]);
    [$one, $two] = startConcurrentWorkers(
        ['operation' => 'issue', 'actor_id' => $actor->id, 'data' => ['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '7', 'reason' => 'Concurrent issue test']],
        ['operation' => 'reserve', 'actor_id' => $actor->id, 'data' => ['product_id' => $product->id, 'location_id' => $location->id, 'quantity' => '7', 'purpose' => 'internal_use', 'reason' => 'Concurrent reservation test']],
    );
    $results = [assertConcurrencyWorkerCompleted($one), assertConcurrencyWorkerCompleted($two)];
    expect(collect($results)->where('ok', true)->count())->toBe(1);
    expect(collect($results)->where('ok', false)->count())->toBe(1);

    $stock = InventoryStock::query()->where('product_id', $product->id)->where('location_id', $location->id)->firstOrFail();
    expect(bccomp((string) $stock->quantity, (string) $stock->reserved_quantity, 4))->toBeGreaterThanOrEqual(0);
    expect(bccomp(bcsub((string) $stock->quantity, (string) $stock->reserved_quantity, 4), '0', 4))->toBeGreaterThanOrEqual(0);
});

test('simultaneous transfers with different idempotency keys cannot overspend one source stock row', function () {
    $sender = concurrencyUser(['system.inventory.transfer', 'system.inventory.view']);
    $receiver = concurrencyUser(['system.inventory.transfer', 'system.inventory.view']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();
    $sender->locations()->attach($source->id, ['access_level' => 'manage']);
    $receiver->locations()->attach($destination->id, ['access_level' => 'receive']);
    InventoryStock::query()->create([
        'product_id' => $product->id,
        'location_id' => $source->id,
        'quantity' => '10.0000',
        'reserved_quantity' => '0.0000',
        'low_stock_threshold' => '0.0000',
    ]);

    $first = [
        'operation' => 'dispatch',
        'actor_id' => $sender->id,
        'data' => [
            'product_id' => $product->id,
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'quantity' => '7',
            'idempotency_key' => (string) Str::uuid(),
            'reason' => 'Competing transfer A',
        ],
    ];
    $second = [
        'operation' => 'dispatch',
        'actor_id' => $sender->id,
        'data' => [
            'product_id' => $product->id,
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'quantity' => '7',
            'idempotency_key' => (string) Str::uuid(),
            'reason' => 'Competing transfer B',
        ],
    ];

    [$one, $two] = startConcurrentWorkers($first, $second);
    $results = [assertConcurrencyWorkerCompleted($one), assertConcurrencyWorkerCompleted($two)];

    expect(collect($results)->where('ok', true)->count())->toBe(1);
    expect(collect($results)->where('ok', false)->count())->toBe(1);
    expect(InventoryTransfer::query()->count())->toBe(1);
    expect((string) InventoryStock::query()->where('product_id', $product->id)->where('location_id', $source->id)->value('quantity'))->toBe('3.0000');
    expect(DB::table('stock_movements')->where('location_id', $source->id)->where('movement_type', 'transfer_out')->count())->toBe(1);
});


test('simultaneous B2B orders cannot exceed one account credit limit across different locations', function () {
    $actor = User::factory()->create();
    $customer = Customer::query()->create([
        'customer_code' => 'CUS-'.Str::upper(Str::random(8)),
        'name' => 'Concurrent Credit Customer',
        'status' => 'active',
    ]);
    $account = B2BAccount::query()->create([
        'account_code' => 'B2B-'.Str::upper(Str::random(8)),
        'company_name' => 'Concurrent Credit Account',
        'business_type' => 'retailer',
        'tier' => 'gold',
        'status' => 'active',
        'customer_id' => $customer->id,
        'payment_terms' => 'net_30',
        'credit_limit' => 150,
        'default_discount_percent' => 0,
    ]);

    $product = inventoryTestProduct('variable');
    $variant = inventoryTestVariant($product);
    // Each concurrent order must consume BDT 100 against the BDT 150 limit.
    // The shared helper defaults to BDT 20, which would let both orders pass.
    $variant->update(['selling_price' => 100]);
    $firstLocation = inventoryTestLocation('credit-a-'.Str::lower(Str::random(5)));
    $secondLocation = inventoryTestLocation('credit-b-'.Str::lower(Str::random(5)));
    foreach ([$firstLocation, $secondLocation] as $location) {
        InventoryStock::query()->create([
            'product_variant_id' => $variant->id,
            'location_id' => $location->id,
            'quantity' => '10.0000',
            'reserved_quantity' => '0.0000',
            'low_stock_threshold' => '0.0000',
        ]);
    }
    InventoryReservationPurpose::query()->firstOrCreate(
        ['key' => 'customer_order'],
        ['label' => 'Customer order', 'is_active' => true, 'sort_order' => 10]
    );

    $firstPayload = [
        'location_id' => $firstLocation->id,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'channel' => 'backoffice',
        'items' => [['product_variant_id' => $variant->id, 'quantity' => 1]],
    ];
    $secondPayload = array_replace($firstPayload, ['location_id' => $secondLocation->id]);

    [$one, $two] = startConcurrentWorkers(
        ['operation' => 'b2b_order', 'actor_id' => $actor->id, 'data' => $firstPayload],
        ['operation' => 'b2b_order', 'actor_id' => $actor->id, 'data' => $secondPayload],
    );
    $results = [assertConcurrencyWorkerCompleted($one), assertConcurrencyWorkerCompleted($two)];

    expect(collect($results)->where('ok', true)->count())->toBe(1);
    expect(collect($results)->where('ok', false)->count())->toBe(1);
    expect(SalesOrder::query()->where('b2b_account_id', $account->id)->count())->toBe(1);
    expect((string) SalesOrder::query()->where('b2b_account_id', $account->id)->sum('grand_total'))->toBe('100.00');
    expect(app(B2BCreditService::class)->summary($account->fresh())['outstanding'])->toBe('100.00');
});
