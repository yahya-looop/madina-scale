<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 2);

require $root . '/vendor/autoload.php';

$app = require $root . '/bootstrap/app.php';
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

function assertVariantRaceTestDatabase(): void
{
    $database = DB::connection('pgsql')
        ->selectOne('SELECT current_database() AS name')->name;

    if ($database !== 'madina_scale_test') {
        throw new RuntimeException(
            "Safety stop: unexpected database {$database}"
        );
    }
}

function waitForVariantRaceGate(
    string $gate,
    string $result
): void {
    $deadline = microtime(true) + 30;

    while (!is_file($gate)) {
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

function runVariantRaceWorker(
    string $gate,
    string $result,
    int $productId,
    string $sku
): never {
    assertVariantRaceTestDatabase();

    waitForVariantRaceGate($gate, $result);

    try {
        DB::transaction(function () use ($productId, $sku) {
            $now = now()->toDateTimeString();

            // Intentionally bypass application-level validation.
            // PostgreSQL must enforce identifier uniqueness itself.
            DB::table('product_variants')->insert([
                'product_id' => $productId,
                'name' => 'Concurrent SKU Race Variant',
                'sku' => $sku,
                'barcode' => null,
                'cost_price' => 10,
                'selling_price' => 20,
                'wholesale_price' => null,
                'promotional_price' => null,
                'promotion_starts_at' => null,
                'promotion_ends_at' => null,
                'is_default' => false,
                'status' => 'active',
                'sort_order' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        });

        file_put_contents($result, json_encode([
            'status' => 'success',
            'product_id' => $productId,
            'sku' => $sku,
        ]));

        exit(0);
    } catch (QueryException $e) {
        $state = (string) ($e->errorInfo[0] ?? $e->getCode());

        file_put_contents($result, json_encode([
            'status' => $state === '23505' ? 'duplicate' : 'error',
            'product_id' => $productId,
            'sqlstate' => $state,
            'message' => $e->getMessage(),
        ]));

        exit($state === '23505' ? 0 : 3);
    } catch (Throwable $e) {
        file_put_contents($result, json_encode([
            'status' => 'error',
            'product_id' => $productId,
            'message' => $e->getMessage(),
        ]));

        exit(4);
    }
}

if (($argv[1] ?? '') === 'worker') {
    runVariantRaceWorker(
        $argv[2],
        $argv[3],
        (int) $argv[4],
        $argv[5]
    );
}

assertVariantRaceTestDatabase();

$token = strtolower(str_replace('-', '', (string) str()->uuid()));
$sku = 'VARIANT-RACE-' . strtoupper($token);

$categorySlug = 'variant-race-category-' . $token;
$unitSlug = 'variant-race-unit-' . $token;
$productSlugA = 'variant-race-product-a-' . $token;
$productSlugB = 'variant-race-product-b-' . $token;

$directory = $root . '/storage/framework/testing';

if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
    throw new RuntimeException('Could not create test directory.');
}

$gate = $directory . '/variant-race-' . $token . '.go';

$results = [
    $directory . '/variant-race-' . $token . '-a.json',
    $directory . '/variant-race-' . $token . '-b.json',
];

$processes = [];
$categoryId = null;
$unitId = null;
$productIdA = null;
$productIdB = null;
$passed = false;

try {
    $now = now()->toDateTimeString();

    $categoryId = DB::table('product_categories')->insertGetId([
        'name' => 'Variant Race Category ' . $token,
        'slug' => $categorySlug,
        'status' => 'active',
        'visibility' => 'visible',
        'sort_order' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $unitId = DB::table('units')->insertGetId([
        'name' => 'Variant Race Unit ' . $token,
        'short_name' => 'VR',
        'slug' => $unitSlug,
        'status' => 'active',
        'sort_order' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Separate variable products ensure the test exercises
    // global SKU uniqueness across different parents.
    $productIdA = DB::table('products')->insertGetId([
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'name' => 'Variant Race Product A',
        'slug' => $productSlugA,
        'sku' => null,
        'barcode' => null,
        'product_type' => 'variable',
        'status' => 'active',
        'visibility' => 'visible',
        'sort_order' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $productIdB = DB::table('products')->insertGetId([
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'name' => 'Variant Race Product B',
        'slug' => $productSlugB,
        'sku' => null,
        'barcode' => null,
        'product_type' => 'variable',
        'status' => 'active',
        'visibility' => 'visible',
        'sort_order' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $commands = [
        [
            PHP_BINARY,
            __FILE__,
            'worker',
            $gate,
            $results[0],
            (string) $productIdA,
            $sku,
        ],
        [
            PHP_BINARY,
            __FILE__,
            'worker',
            $gate,
            $results[1],
            (string) $productIdB,
            $sku,
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

        if (!is_resource($process)) {
            throw new RuntimeException('Could not start a worker.');
        }

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $processes[] = $process;
    }

    // Release both workers as close together as possible.
    touch($gate);

    $exitCodes = [];

    foreach ($processes as $process) {
        $exitCodes[] = proc_close($process);
    }

    $processes = [];

    $outcomes = [];

    foreach ($results as $file) {
        if (!is_file($file)) {
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

    $activeIdentifiers = DB::table('product_identifiers')
        ->where('identifier_type', 'sku')
        ->where('normalized_value', strtolower($sku))
        ->whereNull('deleted_at')
        ->get();

    $activeVariants = DB::table('product_variants')
        ->whereIn('product_id', [$productIdA, $productIdB])
        ->whereRaw('LOWER(sku) = ?', [strtolower($sku)])
        ->whereNull('deleted_at')
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
        && $activeIdentifiers->count() === 1
        && $activeVariants->count() === 1
        && count($exitCodes) === 2
        && $exitCodes[0] === 0
        && $exitCodes[1] === 0;

    echo "\n=== PRODUCT VARIANT SKU RACE TEST ===\n";
    echo "Database: madina_scale_test\n";
    echo "SKU: {$sku}\n";
    echo "Worker A: "
        . json_encode($outcomes[0], JSON_PRETTY_PRINT) . "\n";
    echo "Worker B: "
        . json_encode($outcomes[1], JSON_PRETTY_PRINT) . "\n";
    echo "Active matching identifiers: "
        . $activeIdentifiers->count() . "\n";
    echo "Active matching variants: "
        . $activeVariants->count() . "\n";
    echo "Worker exit codes: " . json_encode($exitCodes) . "\n";

    echo $passed
        ? "\nPASS: exactly one variant claimed the SKU; global uniqueness holds.\n"
        : "\nFAIL: variant SKU race expectations were not satisfied.\n";
} finally {
    foreach ($processes as $process) {
        if (is_resource($process)) {
            proc_close($process);
        }
    }

    // Remove only records created by this run.
    DB::transaction(function () use (
        $productSlugA,
        $productSlugB,
        $categoryId,
        $unitId
    ) {
        $productIds = DB::table('products')
            ->whereIn('slug', [$productSlugA, $productSlugB])
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
