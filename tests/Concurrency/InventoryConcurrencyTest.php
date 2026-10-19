<?php

use App\Models\InventoryStock;
use App\Models\InventoryTransfer;
use App\Models\Module;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Process\PendingProcess;
use Symfony\Component\Process\Process;
use Illuminate\Support\Str;

function concurrencyUser(array $permissionSlugs): User
{
    $user = User::factory()->create();
    $module = Module::firstOrCreate(['slug' => 'inventory'], [
        'name' => 'Inventory', 'description' => 'Inventory management.',
        'sort_order' => 236, 'is_system' => true, 'is_active' => true,
    ]);
    $role = Role::create([
        'name' => 'Concurrency Test Role', 'slug' => 'concurrency-' . Str::uuid(),
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
    $barrier = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'madina-concurrency-' . Str::uuid() . '.go';
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
    foreach ([$first, $second] as &$payload) {
        $payload['barrier'] = $barrier;
    }
    unset($payload);
    $one = new Process([PHP_BINARY, $script, base64_encode(json_encode($first, JSON_THROW_ON_ERROR))], base_path(), $env, null, 35);
    $two = new Process([PHP_BINARY, $script, base64_encode(json_encode($second, JSON_THROW_ON_ERROR))], base_path(), $env, null, 35);
    $one->start();
    $two->start();
    // Give both child processes time to boot and wait at the barrier.
    usleep(500_000);
    file_put_contents($barrier, 'go');
    $one->wait();
    $two->wait();
    @unlink($barrier);
    return [$one, $two, $barrier];
}

function assertConcurrencyWorkerCompleted(Process $process): array
{
    expect($process->isSuccessful() || $process->getExitCode() === 2)->toBeTrue(
        'Worker failed unexpectedly. STDOUT: ' . $process->getOutput() . ' STDERR: ' . $process->getErrorOutput()
    );
    $line = trim($process->getOutput());
    $result = json_decode($line, true);
    expect($result)->toBeArray('Worker did not return JSON: ' . $line . ' ' . $process->getErrorOutput());
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
    app(\App\Services\InventoryTransferService::class)->dispatch([
        'product_id' => $product->id, 'source_location_id' => $source->id,
        'destination_location_id' => $destination->id, 'quantity' => '5',
    ], $sender);
    $transfer = InventoryTransfer::query()->firstOrFail();
    $payload = ['operation' => 'receive', 'transfer_id' => $transfer->id, 'data' => ['quantity_received' => '5']];
    [$one, $two] = startConcurrentWorkers(
        $payload + ['actor_id' => $receiverOne->id],
        $payload + ['actor_id' => $receiverTwo->id],
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
