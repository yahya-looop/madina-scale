<?php

use App\Services\InventoryService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

test('stock movement history rejects updates at the database level', function (): void {
    $product = inventoryTestProduct();
    $location = inventoryTestLocation();

    $movement = app(InventoryService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '5.0000',
    ]);

    try {
        DB::transaction(function () use ($movement): void {
            DB::table('stock_movements')
                ->where('id', $movement->id)
                ->update(['reason' => 'tampered']);
        });

        test()->fail('The database accepted an update to immutable stock movement history.');
    } catch (QueryException) {
        // The nested transaction savepoint keeps the outer test transaction usable.
    }

    expect(DB::table('stock_movements')->where('id', $movement->id)->value('reason'))
        ->toBeNull();
});

test('stock movement history rejects deletes at the database level', function (): void {
    $product = inventoryTestProduct();
    $location = inventoryTestLocation();

    $movement = app(InventoryService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '5.0000',
    ]);

    try {
        DB::transaction(function () use ($movement): void {
            DB::table('stock_movements')
                ->where('id', $movement->id)
                ->delete();
        });

        test()->fail('The database accepted a delete from immutable stock movement history.');
    } catch (QueryException) {
        // The nested transaction savepoint keeps the outer test transaction usable.
    }

    expect(DB::table('stock_movements')->where('id', $movement->id)->exists())
        ->toBeTrue();
});

test('inventory reconciliation reports a consistent stock ledger', function (): void {
    $product = inventoryTestProduct();
    $location = inventoryTestLocation();
    $inventory = app(InventoryService::class);

    $inventory->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '8.0000',
    ]);

    $inventory->issue([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '3.0000',
    ]);

    $this->artisan('inventory:reconcile')
        ->expectsOutput('Stock balance mismatches: 0')
        ->expectsOutput('Ledger balances without matching stock rows: 0')
        ->expectsOutput('Broken movement balance chains: 0')
        ->assertExitCode(0);
});

test('inventory reconciliation detects stock balance drift without changing records', function (): void {
    $product = inventoryTestProduct();
    $location = inventoryTestLocation();

    app(InventoryService::class)->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '8.0000',
    ]);

    DB::table('inventory_stocks')
        ->where('product_id', $product->id)
        ->where('location_id', $location->id)
        ->update(['quantity' => '7.0000']);

    $this->artisan('inventory:reconcile')
        ->expectsOutput('Stock balance mismatches: 1')
        ->expectsOutput('Inventory inconsistencies detected. No records were changed.')
        ->assertExitCode(1);

    expect(DB::table('inventory_stocks')
        ->where('product_id', $product->id)
        ->where('location_id', $location->id)
        ->value('quantity'))->toBe('7.0000');
});

test('inventory reconciliation detects a non-zero opening ledger balance', function (): void {
    $product = inventoryTestProduct();
    $location = inventoryTestLocation();
    $now = now();

    DB::table('inventory_stocks')->insert([
        'product_id' => $product->id,
        'product_variant_id' => null,
        'location_id' => $location->id,
        'quantity' => '5.0000',
        'reserved_quantity' => '0.0000',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    DB::table('stock_movements')->insert([
        'product_id' => $product->id,
        'product_variant_id' => null,
        'location_id' => $location->id,
        'movement_type' => 'receipt',
        'quantity_delta' => '3.0000',
        'balance_before' => '2.0000',
        'balance_after' => '5.0000',
        'created_at' => $now,
        'updated_at' => $now,
    ]);

    $this->artisan('inventory:reconcile')
        ->expectsOutput('Stock balance mismatches: 0')
        ->expectsOutput('Ledger balances without matching stock rows: 0')
        ->expectsOutput('Broken movement balance chains: 1')
        ->expectsOutput('Inventory inconsistencies detected. No records were changed.')
        ->assertExitCode(1);
});

test('inventory reconciliation detects a missing stock row even when the ledger ends at zero', function (): void {
    $product = inventoryTestProduct();
    $location = inventoryTestLocation();
    $inventory = app(InventoryService::class);

    $inventory->receive([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '5.0000',
    ]);

    $inventory->issue([
        'product_id' => $product->id,
        'location_id' => $location->id,
        'quantity' => '5.0000',
    ]);

    DB::table('inventory_stocks')
        ->where('product_id', $product->id)
        ->where('location_id', $location->id)
        ->delete();

    $this->artisan('inventory:reconcile')
        ->expectsOutput('Stock balance mismatches: 0')
        ->expectsOutput('Ledger balances without matching stock rows: 1')
        ->expectsOutput('Broken movement balance chains: 0')
        ->expectsOutput('Inventory inconsistencies detected. No records were changed.')
        ->assertExitCode(1);
});
