<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

$root = dirname(__DIR__, 2);

require $root . '/vendor/autoload.php';

$app = require $root . '/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/*
 * Safety boundary: force every process to use the test database.
 */
config([
    'database.default' => 'pgsql',
    'database.connections.pgsql.url' => null,
    'database.connections.pgsql.host' => '127.0.0.1',
    'database.connections.pgsql.port' => '5432',
    'database.connections.pgsql.database' => 'madina_scale_test',
    'database.connections.pgsql.username' => 'madina_scale',
]);

DB::purge('pgsql');

function assertTestDatabase(): void
{
    $database = DB::connection('pgsql')
        ->selectOne('SELECT current_database() AS name')
        ->name;

    if ($database !== 'madina_scale_test') {
        throw new RuntimeException(
            "Safety stop: unexpected database: {$database}"
        );
    }
}

function runWorker(
    string $mode,
    string $slug,
    string $sku,
    string $gate,
    string $result,
    int $categoryId,
    int $unitId,
): never {
    assertTestDatabase();

    $deadline = microtime(true) + 30;

    while (!is_file($gate)) {
        if (microtime(true) > $deadline) {
            file_put_contents($result, json_encode([
                'status' => 'error',
                'message' => 'Timed out waiting for the start gate.',
            ]));
            exit(2);
        }

        usleep(10000);
    }

    try {
        DB::transaction(function () use (
            $mode,
            $slug,
            $sku,
            $categoryId,
            $unitId
        ) {
            $now = now()->toDateTimeString();

            if ($mode === 'simple') {
                DB::table('products')->insert([
                    'category_id' => $categoryId,
                    'unit_id' => $unitId,
                    'name' => 'Concurrency Simple Product',
                    'slug' => $slug,
                    'sku' => $sku,
                    'barcode' => null,
                    'product_type' => 'simple',
                    'status' => 'active',
                    'visibility' => 'visible',
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return;
            }

            if ($mode === 'variant') {
                $productId = DB::table('products')->insertGetId([
                    'category_id' => $categoryId,
                    'unit_id' => $unitId,
                    'name' => 'Concurrency Variable Product',
                    'slug' => $slug,
                    'sku' => null,
                    'barcode' => null,
                    'product_type' => 'variable',
                    'status' => 'active',
                    'visibility' => 'visible',
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                DB::table('product_variants')->insert([
                    'product_id' => $productId,
                    'name' => 'Concurrency Test Variant',
                    'sku' => $sku,
                    'barcode' => null,
                    'cost_price' => 10,
                    'selling_price' => 15,
                    'is_default' => true,
                    'status' => 'active',
                    'sort_order' => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                return;
            }

            throw new RuntimeException("Unknown worker mode: {$mode}");
        });

        file_put_contents($result, json_encode([
            'status' => 'success',
            'mode' => $mode,
        ]));
    } catch (QueryException $e) {
        $sqlState = (string) ($e->errorInfo[0] ?? $e->getCode());

        file_put_contents($result, json_encode([
            'status' => $sqlState === '23505' ? 'duplicate' : 'error',
            'mode' => $mode,
            'sqlstate' => $sqlState,
            'message' => $e->getMessage(),
        ]));

        exit($sqlState === '23505' ? 0 : 3);
    } catch (Throwable $e) {
        file_put_contents($result, json_encode([
            'status' => 'error',
            'mode' => $mode,
            'message' => $e->getMessage(),
        ]));

        exit(4);
    }

    exit(0);
}

if (($argv[1] ?? '') === 'worker') {
    runWorker(
        $argv[2],
        $argv[3],
        $argv[4],
        $argv[5],
        $argv[6],
        (int) $argv[7],
        (int) $argv[8],
    );
}

assertTestDatabase();

$token = strtolower(str_replace('-', '', (string) str()->uuid()));
$sku = 'RACE-' . strtoupper($token);

$simpleSlug = 'race-simple-' . $token;
$variableSlug = 'race-variable-' . $token;

$directory = $root . '/storage/framework/testing';

if (!is_dir($directory)) {
    mkdir($directory, 0777, true);
}

$gate = $directory . '/identifier-race-' . $token . '.go';
$results = [
    $directory . '/identifier-race-' . $token . '-simple.json',
    $directory . '/identifier-race-' . $token . '-variant.json',
];

$processes = [];
$categoryId = null;
$unitId = null;
$passed = false;

try {
    $now = now()->toDateTimeString();

    $categoryId = DB::table('product_categories')->insertGetId([
        'name' => 'Race Category ' . $token,
        'slug' => 'race-category-' . $token,
        'status' => 'active',
        'visibility' => 'visible',
        'sort_order' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $unitId = DB::table('units')->insertGetId([
        'name' => 'Race Unit ' . $token,
        'short_name' => 'RT',
        'slug' => 'race-unit-' . $token,
        'status' => 'active',
        'sort_order' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $workerFile = __FILE__;

    $commands = [
        [
            PHP_BINARY, $workerFile, 'worker',
            'variant', $variableSlug, $sku,
            $gate, $results[1],
            (string) $categoryId, (string) $unitId,
        ],
        [
            PHP_BINARY, $workerFile, 'worker',
            'simple', $simpleSlug, $sku,
            $gate, $results[0],
            (string) $categoryId, (string) $unitId,
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
            $root,
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Could not start a worker process.');
        }

        foreach ($pipes as $pipe) {
            fclose($pipe);
        }

        $processes[] = $process;
    }

    // Both workers wait here until this file appears.
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
                'A worker did not produce a result file.'
            );
        }

        $outcomes[] = json_decode(
            file_get_contents($file),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    $activeCount = DB::table('product_identifiers')
        ->where('identifier_type', 'sku')
        ->where('normalized_value', strtolower($sku))
        ->whereNull('deleted_at')
        ->count();

    $successCount = count(array_filter(
        $outcomes,
        fn ($item) => ($item['status'] ?? null) === 'success',
    ));

    $duplicateCount = count(array_filter(
        $outcomes,
        fn ($item) => ($item['status'] ?? null) === 'duplicate',
    ));

    $errorCount = count(array_filter(
        $outcomes,
        fn ($item) => ($item['status'] ?? null) === 'error',
    ));

    $passed = $successCount === 1
        && $duplicateCount === 1
        && $errorCount === 0
        && $activeCount === 1;

    echo "\n=== SKU CONCURRENCY TEST ===\n";
    echo "Database: madina_scale_test\n";
    echo "SKU: {$sku}\n";
    echo "Simple product result: "
        . json_encode($outcomes[0], JSON_PRETTY_PRINT) . "\n";
    echo "Variant result: "
        . json_encode($outcomes[1], JSON_PRETTY_PRINT) . "\n";
    echo "Active matching identifiers: {$activeCount}\n";
    echo "Exit codes: " . json_encode($exitCodes) . "\n";
    echo $passed
        ? "\nPASS: exactly one write succeeded; one was rejected.\n"
        : "\nFAIL: concurrency expectations were not satisfied.\n";
} finally {
    // Wait for every launched process before cleaning up.
    foreach ($processes as $process) {
        if (is_resource($process)) {
            proc_close($process);
        }
    }

    // Remove only records created by this run.
    if ($categoryId !== null && $unitId !== null) {
        DB::transaction(function () use (
            $simpleSlug,
            $variableSlug,
            $categoryId,
            $unitId
        ) {
            $productIds = DB::table('products')
                ->whereIn('slug', [$simpleSlug, $variableSlug])
                ->pluck('id');

            if ($productIds->isNotEmpty()) {
                DB::table('product_variants')
                    ->whereIn('product_id', $productIds)
                    ->delete();

                DB::table('products')
                    ->whereIn('id', $productIds)
                    ->delete();
            }

            DB::table('product_categories')
                ->where('id', $categoryId)
                ->delete();

            DB::table('units')
                ->where('id', $unitId)
                ->delete();
        });
    }

    foreach (array_merge([$gate], $results) as $file) {
        if (is_file($file)) {
            unlink($file);
        }
    }
}

exit($passed ? 0 : 1);
