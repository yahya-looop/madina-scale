<?php

use App\Models\InventoryStock;
use App\Models\StockMovement;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\InventoryValuationService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->location = inventoryTestLocation('valuation-'.strtolower(Str::random(6)));
    $this->product = inventoryTestProduct();
    $this->actor = User::factory()->create();
});

it('starts newly created zero-balance stock as valued and calculates moving weighted average on receipt and issue', function (): void {
    $movement = app(InventoryService::class)->receive([
        'location_id' => $this->location->id,
        'product_id' => $this->product->id,
        'quantity' => '10',
        'unit_cost' => '12.50',
        'reason' => 'Valuation regression receipt',
        'idempotency_key' => (string) Str::uuid(),
    ], $this->actor);

    $stock = InventoryStock::query()
        ->where('location_id', $this->location->id)
        ->where('product_id', $this->product->id)
        ->firstOrFail();

    expect($stock->valuation_status)->toBe('valued')
        ->and($stock->quantity)->toBe('10.0000')
        ->and($stock->inventory_value)->toBe('125.00')
        ->and($stock->average_unit_cost)->toBe('12.500000');

    $issue = app(InventoryService::class)->issue([
        'location_id' => $this->location->id,
        'product_id' => $this->product->id,
        'quantity' => '4',
        'reason' => 'Valuation regression issue',
        'idempotency_key' => (string) Str::uuid(),
    ], $this->actor);

    expect($issue->fresh()->quantity_delta)->toBe('-4.0000')
        ->and($stock->fresh()->quantity)->toBe('6.0000')
        ->and($stock->fresh()->inventory_value)->toBe('75.00')
        ->and($stock->fresh()->average_unit_cost)->toBe('12.500000')
        ->and($movement->fresh()->id)->not->toBeNull();
});

it('requires a verified opening value for legacy positive stock and records it in the immutable valuation ledger', function (): void {
    $stock = InventoryStock::query()->create([
        'product_id' => $this->product->id,
        'product_variant_id' => null,
        'location_id' => $this->location->id,
        'quantity' => '10.0000',
        'reserved_quantity' => '0.0000',
        'average_unit_cost' => null,
        'inventory_value' => null,
        'valuation_status' => 'pending_opening',
    ]);

    expect(fn () => app(InventoryValuationService::class)->setOpeningValue($stock, '250.00', $this->actor, 'Verified opening count'))
        ->not->toThrow(ValidationException::class);

    expect($stock->fresh()->valuation_status)->toBe('valued')
        ->and($stock->fresh()->inventory_value)->toBe('250.00')
        ->and($stock->fresh()->average_unit_cost)->toBe('25.000000')
        ->and($stock->valuationMovements()->where('movement_type', 'opening')->count())->toBe(1);
});

it('rejects stock movement while legacy positive stock has no verified opening valuation', function (): void {
    $stock = InventoryStock::query()->create([
        'product_id' => $this->product->id,
        'product_variant_id' => null,
        'location_id' => $this->location->id,
        'quantity' => '10.0000',
        'reserved_quantity' => '0.0000',
        'average_unit_cost' => null,
        'inventory_value' => null,
        'valuation_status' => 'pending_opening',
    ]);

    expect(fn () => app(InventoryService::class)->issue([
        'location_id' => $this->location->id,
        'product_id' => $this->product->id,
        'quantity' => '1',
        'reason' => 'Must not bypass opening valuation',
    ], $this->actor))->toThrow(ValidationException::class);

    expect($stock->fresh()->quantity)->toBe('10.0000')
        ->and($stock->fresh()->valuation_status)->toBe('pending_opening')
        ->and(StockMovement::query()->where('location_id', $this->location->id)->count())->toBe(0);
});

it('reconciles the full historical valuation chain and linked stock movement balances', function (): void {
    app(InventoryService::class)->receive([
        'location_id' => $this->location->id,
        'product_id' => $this->product->id,
        'quantity' => '10.0000',
        'unit_cost' => '12.500000',
        'idempotency_key' => (string) Str::uuid(),
    ], $this->actor);
    app(InventoryService::class)->issue([
        'location_id' => $this->location->id,
        'product_id' => $this->product->id,
        'quantity' => '2.0000',
        'idempotency_key' => (string) Str::uuid(),
    ], $this->actor);

    $this->artisan('inventory:valuation:reconcile')
        ->expectsOutput('Historical valuation chain mismatches: 0')
        ->expectsOutput('Valuation-to-stock-movement mismatches: 0')
        ->expectsOutput('Valuation-to-current-stock mismatches: 0')
        ->assertExitCode(0);
});
