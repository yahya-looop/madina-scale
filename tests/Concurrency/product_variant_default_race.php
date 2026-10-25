<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 2);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config([
    'database.default' => 'pgsql',
    'database.connections.pgsql.url' => null,
    'database.connections.pgsql.host' => '127.0.0.1',
    'database.connections.pgsql.port' => '5432',
    'database.connections.pgsql.database' => 'madina_scale_test',
    'database.connections.pgsql.username' => 'madina_scale',
]);

DB::purge('pgsql');

function assertDefaultRaceTestDatabase(): void
{
    $database = DB::connection('pgsql')
        ->selectOne('SELECT current_database() AS name')->name;

    if ($database !== 'madina_scale_test') {
        throw new RuntimeException(
            "Safety stop: unexpected database {$database}"
        );
    }
}

function waitForDefaultRaceGate(string $gate, string $result): void
{
    $deadline = microtime(true) + 30;

    while (! is_file($gate)) {
        if (microtime(true) > $deadline) {
            file_put_contents($result, json_encode([
                'status' => 'error',
                'message' => 'Timed out waiting for race gate.',
            ]));

            exit(2);
        }

        usleep(10000);
    }
}

function runDefaultRaceWorker(
    string $gate,
    string $result,
    int $variantId
): never {
    assertDefaultRaceTestDatabase();
    waitForDefaultRaceGate($gate, $result);

    try {
        DB::transaction(function () use ($variantId) {
            DB::table('product_variants')
                ->where('id', $variantId)
                ->whereNull('deleted_at')
                ->update([
                    'is_default' => true,
                    'updated_at' => now()->toDateTimeString(),
                ]);
        });

        file_put_contents($result, json_encode([
            'status' => 'success',
            'variant_id' => $variantId,
        ]));

        exit(0);
    } catch (QueryException $e) {
        $state = (string) ($e->errorInfo[0] ?? $e->getCode());

        file_put_contents($result, json_encode([
            'status' => $state === '23505' ? 'duplicate' : 'error',
            'variant_id' => $variantId,
            'sqlstate' => $state,
            'message' => $e->getMessage(),
        ]));

        exit($state === '23505' ? 0 : 3);
    } catch (Throwable $e) {
        file_put_contents($result, json_encode([
            'status' => 'error',
            'variant_id' => $variantId,
            'message' => $e->getMessage(),
        ]));

        exit(4);
    }
}

if (($argv[1] ?? '') === 'worker') {
    runDefaultRaceWorker(
        $argv[2],
        $argv[3],
        (int) $argv[4]
    );
}

assertDefaultRaceTestDatabase();

$token = strtolower(str_replace('-', '', (string) str()->uuid()));

$categorySlug = 'default-race-category-'.$token;
$unitSlug = 'default-race-unit-'.$token;
$productSlug = 'default-race-product-'.$token;

$directory = $root.'/storage/framework/testing';

if (! is_dir($directory)
    && ! mkdir($directory, 0777, true)
    && ! is_dir($directory)
) {
    throw new RuntimeException('Could not create test directory.');
}

$gate = $directory.'/default-race-'.$token.'.go';

$results = [
    $directory.'/default-race-'.$token.'-a.json',
    $directory.'/default-race-'.$token.'-b.json',
];

$processes = [];
$categoryId = null;
$unitId = null;
$productId = null;
$variantIdA = null;
$variantIdB = null;
$passed = false;

try {
    $now = now()->toDateTimeString();

    $categoryId = DB::table('product_categories')->insertGetId([
        'name' => 'Default Race Category '.$token,
        'slug' => $categorySlug,
        'status' => 'active',
        'visibility' => 'visible',
        'sort_order' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $unitId = DB::table('units')->insertGetId([
        'name' => 'Default Race Unit '.$token,
        'short_name' => 'DR',
        'slug' => $unitSlug,
        'status' => 'active',
        'sort_order' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $productId = DB::table('products')->insertGetId([
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'name' => 'Default Race Product',
        'slug' => $productSlug,
        'sku' => null,
        'barcode' => null,
        'product_type' => 'variable',
        'status' => 'active',
        'visibility' => 'visible',
        'sort_order' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $variantIdA = DB::table('product_variants')->insertGetId([
        'product_id' => $productId,
        'name' => 'Default Race Variant A',
        'sku' => 'DEFAULT-A-'.strtoupper($token),
        'barcode' => null,
        'cost_price' => 10,
        'selling_price' => 20,
        'is_default' => false,
        'status' => 'active',
        'sort_order' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $variantIdB = DB::table('product_variants')->insertGetId([
        'product_id' => $productId,
        'name' => 'Default Race Variant B',
        'sku' => 'DEFAULT-B-'.strtoupper($token),
        'barcode' => null,
        'cost_price' => 10,
        'selling_price' => 20,
        'is_default' => false,
        'status' => 'active',
        'sort_order' => 1,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $commands = [
        [
            PHP_BINARY, __FILE__, 'worker',
            $gate, $results[0], (string) $variantIdA,
        ],
        [
            PHP_BINARY, __FILE__, 'worker',
            $gate, $results[1], (string) $variantIdB,
        ],
    ];

    foreach ($commands as $command) {
        $pipes = [];

        $process = proc_open(
            $command,
            [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ],
            $pipes,
            $root
        );

        if (! is_resource($process)) {
            throw new RuntimeException('Could not start a worker.');
        }

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $processes[] = $process;
    }

    touch($gate);

    $exitCodes = [];

    foreach ($processes as $process) {
        $exitCodes[] = proc_close($process);
    }

    $processes = [];

    $outcomes = [];

    foreach ($results as $file) {
        if (! is_file($file)) {
            throw new RuntimeException(
                'A worker did not produce a result.'
            );
        }

        $outcomes[] = json_decode(
            file_get_contents($file),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    $activeDefaults = DB::table('product_variants')
        ->where('product_id', $productId)
        ->whereNull('deleted_at')
        ->where('is_default', true)
        ->get();

    $successCount = count(array_filter(
        $outcomes,
        fn ($outcome) => ($outcome['status'] ?? null) === 'success'
    ));

    $duplicateCount = count(array_filter(
        $outcomes,
        fn ($outcome) => ($outcome['status'] ?? null) === 'duplicate'
    ));

    $passed = $successCount === 1
        && $duplicateCount === 1
        && $activeDefaults->count() === 1
        && count($exitCodes) === 2
        && $exitCodes[0] === 0
        && $exitCodes[1] === 0;

    echo "\n=== PRODUCT VARIANT DEFAULT RACE TEST ===\n";
    echo "Database: madina_scale_test\n";
    echo "Product ID: {$productId}\n";
    echo 'Worker A: '
        .json_encode($outcomes[0], JSON_PRETTY_PRINT)."\n";
    echo 'Worker B: '
        .json_encode($outcomes[1], JSON_PRETTY_PRINT)."\n";
    echo 'Active default variants: '.$activeDefaults->count()."\n";
    echo 'Worker exit codes: '.json_encode($exitCodes)."\n";

    echo $passed
        ? "\nPASS: exactly one active default variant remains.\n"
        : "\nFAIL: default variant race expectations were not satisfied.\n";
} finally {
    foreach ($processes as $process) {
        if (is_resource($process)) {
            proc_close($process);
        }
    }

    DB::transaction(function () use (
        $productSlug,
        $categoryId,
        $unitId
    ) {
        $productIds = DB::table('products')
            ->where('slug', $productSlug)
            ->pluck('id');

        if ($productIds->isNotEmpty()) {
            DB::table('product_variants')
                ->whereIn('product_id', $productIds)
                ->delete();

            DB::table('products')
                ->whereIn('id', $productIds)
                ->delete();
        }

        if ($categoryId !== null) {
            DB::table('product_categories')
                ->where('id', $categoryId)
                ->delete();
        }

        if ($unitId !== null) {
            DB::table('units')
                ->where('id', $unitId)
                ->delete();
        }
    });

    foreach (array_merge([$gate], $results) as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

exit($passed ? 0 : 1);
