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

function assertRestoreTestDatabase(): void
{
    $database = DB::connection('pgsql')
        ->selectOne('SELECT current_database() AS name')->name;

    if ($database !== 'madina_scale_test') {
        throw new RuntimeException(
            "Safety stop: unexpected database {$database}"
        );
    }
}

function waitForRaceGate(string $gate, string $result): void
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

function runRestoreWorker(
    string $mode,
    string $gate,
    string $result,
    string $reuseSlug,
    string $sku,
    int $originalId,
    int $categoryId,
    int $unitId,
): never {
    assertRestoreTestDatabase();
    waitForRaceGate($gate, $result);

    try {
        DB::transaction(function () use (
            $mode,
            $reuseSlug,
            $sku,
            $originalId,
            $categoryId,
            $unitId
        ) {
            $now = now()->toDateTimeString();

            if ($mode === 'reuse') {
                DB::table('products')->insert([
                    'category_id' => $categoryId,
                    'unit_id' => $unitId,
                    'name' => 'Restore Race Replacement',
                    'slug' => $reuseSlug,
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

            if ($mode === 'restore') {
                $affected = DB::table('products')
                    ->where('id', $originalId)
                    ->whereNotNull('deleted_at')
                    ->update([
                        'deleted_at' => null,
                        'updated_at' => $now,
                    ]);

                if ($affected !== 1) {
                    throw new RuntimeException(
                        'Original product was not restored.'
                    );
                }

                return;
            }

            throw new RuntimeException("Unknown worker mode: {$mode}");
        });

        file_put_contents($result, json_encode([
            'status' => 'success',
            'mode' => $mode,
        ]));
    } catch (QueryException $e) {
        $state = (string) ($e->errorInfo[0] ?? $e->getCode());

        file_put_contents($result, json_encode([
            'status' => $state === '23505' ? 'duplicate' : 'error',
            'mode' => $mode,
            'sqlstate' => $state,
            'message' => $e->getMessage(),
        ]));

        exit($state === '23505' ? 0 : 3);
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
    runRestoreWorker(
        $argv[2],
        $argv[3],
        $argv[4],
        $argv[5],
        $argv[6],
        (int) $argv[7],
        (int) $argv[8],
        (int) $argv[9],
    );
}

assertRestoreTestDatabase();

$token = strtolower(str_replace('-', '', (string) str()->uuid()));
$sku = 'RESTORE-RACE-'.strtoupper($token);
$originalSlug = 'restore-race-original-'.$token;
$reuseSlug = 'restore-race-reuse-'.$token;

$directory = $root.'/storage/framework/testing';

if (! is_dir($directory)) {
    mkdir($directory, 0777, true);
}

$gate = $directory.'/restore-race-'.$token.'.go';
$results = [
    $directory.'/restore-race-'.$token.'-reuse.json',
    $directory.'/restore-race-'.$token.'-restore.json',
];

$processes = [];
$categoryId = null;
$unitId = null;
$originalId = null;
$passed = false;

try {
    $now = now()->toDateTimeString();

    $categoryId = DB::table('product_categories')->insertGetId([
        'name' => 'Restore Race Category '.$token,
        'slug' => 'restore-race-category-'.$token,
        'status' => 'active',
        'visibility' => 'visible',
        'sort_order' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $unitId = DB::table('units')->insertGetId([
        'name' => 'Restore Race Unit '.$token,
        'short_name' => 'RR',
        'slug' => 'restore-race-unit-'.$token,
        'status' => 'active',
        'sort_order' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $originalId = DB::table('products')->insertGetId([
        'category_id' => $categoryId,
        'unit_id' => $unitId,
        'name' => 'Restore Race Original',
        'slug' => $originalSlug,
        'sku' => $sku,
        'barcode' => null,
        'product_type' => 'simple',
        'status' => 'active',
        'visibility' => 'visible',
        'sort_order' => 0,
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    // Release the original SKU but retain its identifier history.
    DB::table('products')
        ->where('id', $originalId)
        ->update([
            'deleted_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

    $commands = [
        [
            PHP_BINARY, __FILE__, 'worker',
            'reuse', $gate, $results[0],
            $reuseSlug, $sku, (string) $originalId,
            (string) $categoryId, (string) $unitId,
        ],
        [
            PHP_BINARY, __FILE__, 'worker',
            'restore', $gate, $results[1],
            $reuseSlug, $sku, (string) $originalId,
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
            JSON_THROW_ON_ERROR,
        );
    }

    $active = DB::table('product_identifiers')
        ->where('identifier_type', 'sku')
        ->where('normalized_value', strtolower($sku))
        ->whereNull('deleted_at')
        ->get();

    $original = DB::table('products')
        ->where('id', $originalId)
        ->first();

    $replacement = DB::table('products')
        ->where('slug', $reuseSlug)
        ->first();

    $successCount = count(array_filter(
        $outcomes,
        fn ($o) => ($o['status'] ?? null) === 'success',
    ));

    $duplicateCount = count(array_filter(
        $outcomes,
        fn ($o) => ($o['status'] ?? null) === 'duplicate',
    ));

    $reuseWon = ($outcomes[0]['status'] ?? null) === 'success'
        && ($outcomes[1]['status'] ?? null) === 'duplicate'
        && $original?->deleted_at !== null
        && $replacement !== null;

    $restoreWon = ($outcomes[0]['status'] ?? null) === 'duplicate'
        && ($outcomes[1]['status'] ?? null) === 'success'
        && $original?->deleted_at === null
        && $replacement === null;

    $passed = $successCount === 1
        && $duplicateCount === 1
        && $active->count() === 1
        && ($reuseWon || $restoreWon);

    echo "\n=== SKU RESTORE RACE TEST ===\n";
    echo "Database: madina_scale_test\n";
    echo "SKU: {$sku}\n";
    echo 'Reuse result: '
        .json_encode($outcomes[0], JSON_PRETTY_PRINT)."\n";
    echo 'Restore result: '
        .json_encode($outcomes[1], JSON_PRETTY_PRINT)."\n";
    echo 'Active matching identifiers: '.$active->count()."\n";
    echo 'Original product deleted: '
        .json_encode($original?->deleted_at !== null)."\n";
    echo 'Replacement exists: '
        .json_encode($replacement !== null)."\n";
    echo 'Exit codes: '.json_encode($exitCodes)."\n";
    echo $passed
        ? "\nPASS: exactly one operation won; SKU remains unique.\n"
        : "\nFAIL: restore race expectations were not satisfied.\n";
} finally {
    foreach ($processes as $process) {
        if (is_resource($process)) {
            proc_close($process);
        }
    }

    if ($categoryId !== null && $unitId !== null) {
        DB::transaction(function () use (
            $originalSlug,
            $reuseSlug,
            $categoryId,
            $unitId
        ) {
            $ids = DB::table('products')
                ->whereIn('slug', [$originalSlug, $reuseSlug])
                ->pluck('id');

            if ($ids->isNotEmpty()) {
                DB::table('product_variants')
                    ->whereIn('product_id', $ids)
                    ->delete();

                DB::table('products')
                    ->whereIn('id', $ids)
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
