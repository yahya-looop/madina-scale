
<?php

use App\Models\InventoryStock;
use App\Models\InventoryTransfer;
use App\Services\InventoryTransferService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

test('dispatch moves stock out of source but does not make it available at destination until accepted', function () {
    $sender = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    $receiver = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();
    $sender->locations()->attach($source->id, ['access_level' => 'manage']);
    $receiver->locations()->attach($destination->id, ['access_level' => 'receive']);
    InventoryStock::query()->create([
        'product_id' => $product->id, 'location_id' => $source->id,
        'quantity' => '500.0000', 'reserved_quantity' => '25.0000', 'low_stock_threshold' => '0',
    ]);

    $this->actingAs($sender)->postJson('/api/inventory/transfers', [
        'product_id' => $product->id, 'source_location_id' => $source->id,
        'destination_location_id' => $destination->id, 'quantity' => '300',
        'reason' => 'Online selling point replenishment', 'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated()->assertJsonPath('data.status', 'in_transit');

    $this->assertDatabaseHas('inventory_stocks', ['location_id' => $source->id, 'quantity' => '200.0000', 'reserved_quantity' => '25.0000']);
    $this->assertDatabaseMissing('inventory_stocks', ['location_id' => $destination->id, 'product_id' => $product->id]);
    $transfer = InventoryTransfer::query()->firstOrFail();

    $this->actingAs($receiver)->postJson("/api/inventory/transfers/{$transfer->id}/accept", [
        'quantity_received' => '300', 'recipient_note' => 'Count checked and matched', 'idempotency_key' => (string) Str::uuid(),
    ])->assertOk()->assertJsonPath('data.status', 'received');

    $this->assertDatabaseHas('inventory_stocks', ['location_id' => $destination->id, 'product_id' => $product->id, 'quantity' => '300.0000']);
    $this->assertDatabaseHas('stock_movements', ['transfer_group_id' => $transfer->transfer_uuid, 'location_id' => $source->id, 'movement_type' => 'transfer_out', 'quantity_delta' => '-300.0000']);
    $this->assertDatabaseHas('stock_movements', ['transfer_group_id' => $transfer->transfer_uuid, 'location_id' => $destination->id, 'movement_type' => 'transfer_in', 'quantity_delta' => '300.0000']);
});

test('multiple independent transfers can remain pending for the same product and locations', function () {
    $sender = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    $receiver = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();
    $sender->locations()->attach($source->id, ['access_level' => 'manage']);
    $receiver->locations()->attach($destination->id, ['access_level' => 'receive']);
    InventoryStock::query()->create([
        'product_id' => $product->id, 'location_id' => $source->id,
        'quantity' => '100.0000', 'reserved_quantity' => '0.0000', 'low_stock_threshold' => '0',
    ]);

    foreach (['10', '15'] as $quantity) {
        $this->actingAs($sender)->postJson('/api/inventory/transfers', [
            'product_id' => $product->id,
            'source_location_id' => $source->id,
            'destination_location_id' => $destination->id,
            'quantity' => $quantity,
            'idempotency_key' => (string) Str::uuid(),
        ])->assertCreated()->assertJsonPath('data.status', 'in_transit');
    }

    expect(InventoryTransfer::query()->where('status', 'in_transit')->count())->toBe(2);
    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id, 'location_id' => $source->id, 'quantity' => '75.0000',
    ]);
});

test('partial receipt records only received quantity and leaves transfer outstanding', function () {
    $user = createTransferWorkflowUser(['system.inventory.transfer']);
    $receiver = createTransferWorkflowUser(['system.inventory.transfer']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();
    $user->locations()->attach($source->id, ['access_level' => 'manage']);
    $receiver->locations()->attach($destination->id, ['access_level' => 'receive']);
    InventoryStock::query()->create(['product_id' => $product->id, 'location_id' => $source->id, 'quantity' => '20', 'reserved_quantity' => '0', 'low_stock_threshold' => '0']);
    $this->actingAs($user)->postJson('/api/inventory/transfers', [
        'product_id' => $product->id, 'source_location_id' => $source->id,
        'destination_location_id' => $destination->id, 'quantity' => '12',
    ])->assertCreated();
    $transfer = InventoryTransfer::query()->firstOrFail();
    $this->actingAs($receiver)->postJson("/api/inventory/transfers/{$transfer->id}/accept", ['quantity_received' => '8', 'idempotency_key' => (string) Str::uuid()])
        ->assertOk()->assertJsonPath('data.status', 'partially_received')->assertJsonPath('data.quantity_received', '8.0000');
    $this->assertDatabaseHas('inventory_stocks', ['location_id' => $destination->id, 'quantity' => '8.0000']);
});

test('transfer refuses quantity that would consume reserved source stock', function () {
    $user = createTransferWorkflowUser(['system.inventory.transfer']);
    $receiver = createTransferWorkflowUser(['system.inventory.transfer']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();
    $user->locations()->attach($source->id, ['access_level' => 'manage']);
    $receiver->locations()->attach($destination->id, ['access_level' => 'receive']);
    InventoryStock::query()->create(['product_id' => $product->id, 'location_id' => $source->id, 'quantity' => '10', 'reserved_quantity' => '4', 'low_stock_threshold' => '0']);
    $this->actingAs($user)->postJson('/api/inventory/transfers', [
        'product_id' => $product->id, 'source_location_id' => $source->id,
        'destination_location_id' => $destination->id, 'quantity' => '7',
    ])->assertUnprocessable();
    $this->assertDatabaseCount('inventory_transfers', 0);
});

test('users cannot dispatch from an unassigned location or receive into an unassigned location', function () {
    $sender = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    // This user is intentionally unassigned and is the actor whose receive attempt must be rejected.
    $unassignedReceiver = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    // Dispatch requires at least one eligible receiver; use a separate assigned user so the
    // test can reach the authorization check for the unassigned user.
    $eligibleReceiver = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();
    InventoryStock::query()->create(['product_id' => $product->id, 'location_id' => $source->id, 'quantity' => '10', 'reserved_quantity' => '0', 'low_stock_threshold' => '0']);

    $this->actingAs($sender)->postJson('/api/inventory/transfers', [
        'product_id' => $product->id, 'source_location_id' => $source->id,
        'destination_location_id' => $destination->id, 'quantity' => '2',
    ])->assertUnprocessable();

    $sender->locations()->attach($source->id, ['access_level' => 'manage']);
    $eligibleReceiver->locations()->attach($destination->id, ['access_level' => 'receive']);
    $this->actingAs($sender)->postJson('/api/inventory/transfers', [
        'product_id' => $product->id, 'source_location_id' => $source->id,
        'destination_location_id' => $destination->id, 'quantity' => '2',
    ])->assertCreated();
    $transfer = InventoryTransfer::query()->firstOrFail();

    $this->actingAs($unassignedReceiver)->postJson("/api/inventory/transfers/{$transfer->id}/accept", ['quantity_received' => '2', 'idempotency_key' => (string) Str::uuid()])
        ->assertUnprocessable();
    $this->assertDatabaseHas('inventory_transfers', ['id' => $transfer->id, 'status' => 'in_transit']);
});

test('dispatcher cannot accept their own transfer', function () {
    $user = createTransferWorkflowUser(['system.inventory.transfer']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();
    $user->locations()->attach($source->id, ['access_level' => 'manage']);
    $user->locations()->attach($destination->id, ['access_level' => 'receive']);
    InventoryStock::query()->create(['product_id' => $product->id, 'location_id' => $source->id, 'quantity' => '10', 'reserved_quantity' => '0', 'low_stock_threshold' => '0']);
    $this->actingAs($user)->postJson('/api/inventory/transfers', [
        'product_id' => $product->id, 'source_location_id' => $source->id,
        'destination_location_id' => $destination->id, 'quantity' => '2',
    ])->assertCreated();
    $transfer = InventoryTransfer::query()->firstOrFail();
    $this->postJson("/api/inventory/transfers/{$transfer->id}/accept", ['quantity_received' => '2', 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable();
});

test('dispatch with the same idempotency key replays the existing transfer without moving stock twice', function () {
    $sender = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    $receiver = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
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
    $payload = [
        'product_id' => $product->id,
        'source_location_id' => $source->id,
        'destination_location_id' => $destination->id,
        'quantity' => '5',
        'reason' => 'Idempotency replay test',
        'idempotency_key' => $key,
    ];

    $this->actingAs($sender)->postJson('/api/inventory/transfers', $payload)->assertCreated();
    $firstTransferId = InventoryTransfer::query()->where('idempotency_key', $key)->value('id');
    $this->actingAs($sender)->postJson('/api/inventory/transfers', $payload)->assertCreated();

    $this->assertSame($firstTransferId, InventoryTransfer::query()->where('idempotency_key', $key)->value('id'));
    $this->assertDatabaseCount('inventory_transfers', 1);
    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id, 'location_id' => $source->id,
        'quantity' => '15.0000', 'reserved_quantity' => '0.0000',
    ]);
    $this->assertDatabaseCount('stock_movements', 1);
});

test('a fully received transfer cannot credit destination stock a second time', function () {
    $sender = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    $receiver = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();
    $sender->locations()->attach($source->id, ['access_level' => 'manage']);
    $receiver->locations()->attach($destination->id, ['access_level' => 'receive']);
    InventoryStock::query()->create([
        'product_id' => $product->id, 'location_id' => $source->id,
        'quantity' => '20.0000', 'reserved_quantity' => '0.0000', 'low_stock_threshold' => '0',
    ]);

    $this->actingAs($sender)->postJson('/api/inventory/transfers', [
        'product_id' => $product->id, 'source_location_id' => $source->id,
        'destination_location_id' => $destination->id, 'quantity' => '5',
    ])->assertCreated();
    $transfer = InventoryTransfer::query()->firstOrFail();

    $this->actingAs($receiver)->postJson("/api/inventory/transfers/{$transfer->id}/accept", ['quantity_received' => '5', 'idempotency_key' => (string) Str::uuid()])->assertOk();
    $this->actingAs($receiver)->postJson("/api/inventory/transfers/{$transfer->id}/accept", ['quantity_received' => '5', 'idempotency_key' => (string) Str::uuid()])->assertUnprocessable();

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id, 'location_id' => $destination->id, 'quantity' => '5.0000',
    ]);
    $this->assertDatabaseCount('stock_movements', 2);
    $this->assertDatabaseHas('inventory_transfers', ['id' => $transfer->id, 'status' => 'received', 'quantity_received' => '5.0000']);
});

test('replaying the same partial receipt key does not credit stock twice', function () {
    $sender = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    $receiver = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();
    $sender->locations()->attach($source->id, ['access_level' => 'manage']);
    $receiver->locations()->attach($destination->id, ['access_level' => 'receive']);
    InventoryStock::query()->create(['product_id' => $product->id, 'location_id' => $source->id, 'quantity' => '30', 'reserved_quantity' => '0', 'low_stock_threshold' => '0']);
    $this->actingAs($sender)->postJson('/api/inventory/transfers', [
        'product_id' => $product->id, 'source_location_id' => $source->id,
        'destination_location_id' => $destination->id, 'quantity' => '20',
    ])->assertCreated();
    $transfer = InventoryTransfer::query()->firstOrFail();
    $key = (string) Str::uuid();
    $payload = ['quantity_received' => '8', 'recipient_note' => 'Partial count', 'idempotency_key' => $key];

    $this->actingAs($receiver)->postJson("/api/inventory/transfers/{$transfer->id}/accept", $payload)->assertOk()
        ->assertJsonPath('data.quantity_received', '8.0000');
    $this->actingAs($receiver)->postJson("/api/inventory/transfers/{$transfer->id}/accept", $payload)->assertOk()
        ->assertJsonPath('data.quantity_received', '8.0000');

    $this->assertDatabaseHas('inventory_stocks', ['product_id' => $product->id, 'location_id' => $destination->id, 'quantity' => '8.0000']);
    $this->assertDatabaseCount('inventory_transfer_receipts', 1);
    $this->assertDatabaseCount('stock_movements', 2);
});

test('receipt idempotency key cannot be reused with a different payload', function () {
    $sender = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    $receiver = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();
    $sender->locations()->attach($source->id, ['access_level' => 'manage']);
    $receiver->locations()->attach($destination->id, ['access_level' => 'receive']);
    InventoryStock::query()->create(['product_id' => $product->id, 'location_id' => $source->id, 'quantity' => '30', 'reserved_quantity' => '0', 'low_stock_threshold' => '0']);
    $this->actingAs($sender)->postJson('/api/inventory/transfers', [
        'product_id' => $product->id, 'source_location_id' => $source->id,
        'destination_location_id' => $destination->id, 'quantity' => '20',
    ])->assertCreated();
    $transfer = InventoryTransfer::query()->firstOrFail();
    $key = (string) Str::uuid();
    $this->actingAs($receiver)->postJson("/api/inventory/transfers/{$transfer->id}/accept", ['quantity_received' => '8', 'idempotency_key' => $key])->assertOk();
    $this->actingAs($receiver)->postJson("/api/inventory/transfers/{$transfer->id}/accept", ['quantity_received' => '9', 'idempotency_key' => $key])->assertUnprocessable();
    $this->assertDatabaseHas('inventory_stocks', ['product_id' => $product->id, 'location_id' => $destination->id, 'quantity' => '8.0000']);
    $this->assertDatabaseCount('inventory_transfer_receipts', 1);
});

test('different receipt keys allow valid successive partial receipts', function () {
    $sender = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    $receiver = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();
    $sender->locations()->attach($source->id, ['access_level' => 'manage']);
    $receiver->locations()->attach($destination->id, ['access_level' => 'receive']);
    InventoryStock::query()->create(['product_id' => $product->id, 'location_id' => $source->id, 'quantity' => '30', 'reserved_quantity' => '0', 'low_stock_threshold' => '0']);
    $this->actingAs($sender)->postJson('/api/inventory/transfers', [
        'product_id' => $product->id, 'source_location_id' => $source->id,
        'destination_location_id' => $destination->id, 'quantity' => '12',
    ])->assertCreated();
    $transfer = InventoryTransfer::query()->firstOrFail();
    $this->actingAs($receiver)->postJson("/api/inventory/transfers/{$transfer->id}/accept", ['quantity_received' => '8', 'idempotency_key' => (string) Str::uuid()])
        ->assertOk()->assertJsonPath('data.status', 'partially_received');
    $this->actingAs($receiver)->postJson("/api/inventory/transfers/{$transfer->id}/accept", ['quantity_received' => '4', 'idempotency_key' => (string) Str::uuid()])
        ->assertOk()->assertJsonPath('data.status', 'received');
    $this->assertDatabaseHas('inventory_stocks', ['product_id' => $product->id, 'location_id' => $destination->id, 'quantity' => '12.0000']);
    $this->assertDatabaseCount('inventory_transfer_receipts', 2);
});

test('dispatch idempotency key rejects a changed audit payload', function () {
    $sender = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    $receiver = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
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
    $base = [
        'product_id' => $product->id,
        'source_location_id' => $source->id,
        'destination_location_id' => $destination->id,
        'quantity' => '5',
        'reason' => 'Branch replenishment',
        'reference_type' => 'purchase_order',
        'reference_id' => 'PO-1001',
        'idempotency_key' => $key,
    ];

    $this->actingAs($sender)->postJson('/api/inventory/transfers', $base)->assertCreated();
    $this->actingAs($sender)->postJson('/api/inventory/transfers', [
        ...$base,
        'reason' => 'Different reason for the same key',
    ])->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');

    $this->assertDatabaseCount('inventory_transfers', 1);
    $this->assertDatabaseCount('stock_movements', 1);
    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id, 'location_id' => $source->id, 'quantity' => '15.0000',
    ]);
});

test('discrepancy report preserves an audit record and never silently restores source stock', function () {
    $sender = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    $receiver = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();
    $sender->locations()->attach($source->id, ['access_level' => 'manage']);
    $receiver->locations()->attach($destination->id, ['access_level' => 'receive']);
    InventoryStock::query()->create([
        'product_id' => $product->id, 'location_id' => $source->id,
        'quantity' => '20.0000', 'reserved_quantity' => '0.0000', 'low_stock_threshold' => '0',
    ]);

    $this->actingAs($sender)->postJson('/api/inventory/transfers', [
        'product_id' => $product->id,
        'source_location_id' => $source->id,
        'destination_location_id' => $destination->id,
        'quantity' => '10',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();
    $transfer = InventoryTransfer::query()->firstOrFail();
    $this->actingAs($receiver)->postJson("/api/inventory/transfers/{$transfer->id}/accept", [
        'quantity_received' => '3',
        'idempotency_key' => (string) Str::uuid(),
        'recipient_note' => 'Only three units arrived.',
    ])->assertOk()->assertJsonPath('data.status', 'partially_received');

    $this->actingAs($receiver)->postJson("/api/inventory/transfers/{$transfer->id}/reject", [
        'reason' => 'Two units are missing and the package seal was broken.',
    ])->assertOk()->assertJsonPath('data.status', 'discrepancy');

    $transfer->refresh();
    expect($transfer->metadata['discrepancy']['reason'])->toBe('Two units are missing and the package seal was broken.');
    expect((int) $transfer->metadata['discrepancy']['reported_by'])->toBe((int) $receiver->id);
    expect($transfer->metadata['discrepancy']['reported_at'])->not->toBeEmpty();
    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id, 'location_id' => $source->id,
        'quantity' => '10.0000', 'reserved_quantity' => '0.0000',
    ]);
    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id, 'location_id' => $destination->id,
        'quantity' => '3.0000', 'reserved_quantity' => '0.0000',
    ]);
    $this->assertDatabaseCount('inventory_transfer_receipts', 1);
    $this->assertDatabaseCount('stock_movements', 2);
});

test('dispatch notification failure rolls back transfer and stock changes atomically', function () {
    $sender = createTransferWorkflowUser(['system.inventory.transfer']);
    $receiver = createTransferWorkflowUser(['system.inventory.transfer']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();
    $sender->locations()->attach($source->id, ['access_level' => 'manage']);
    $receiver->locations()->attach($destination->id, ['access_level' => 'receive']);
    InventoryStock::query()->create([
        'product_id' => $product->id, 'location_id' => $source->id,
        'quantity' => '25.0000', 'reserved_quantity' => '0.0000', 'low_stock_threshold' => '0',
    ]);

    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Simulated notification storage failure.'));

    expect(fn () => app(InventoryTransferService::class)->dispatch([
        'product_id' => $product->id,
        'source_location_id' => $source->id,
        'destination_location_id' => $destination->id,
        'quantity' => '10',
        'idempotency_key' => (string) Str::uuid(),
    ], $sender))->toThrow(RuntimeException::class, 'Simulated notification storage failure.');

    $this->assertDatabaseHas('inventory_stocks', [
        'location_id' => $source->id, 'product_id' => $product->id, 'quantity' => '25.0000',
    ]);
    $this->assertDatabaseMissing('inventory_transfers', ['source_location_id' => $source->id, 'destination_location_id' => $destination->id]);
    $this->assertDatabaseMissing('stock_movements', ['location_id' => $source->id, 'movement_type' => 'transfer_out']);
});

test('receipt notification failure rolls back destination stock receipt ledger and transfer status atomically', function () {
    $sender = createTransferWorkflowUser(['system.inventory.transfer']);
    $receiver = createTransferWorkflowUser(['system.inventory.transfer']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();
    $sender->locations()->attach($source->id, ['access_level' => 'manage']);
    $receiver->locations()->attach($destination->id, ['access_level' => 'receive']);
    InventoryStock::query()->create([
        'product_id' => $product->id, 'location_id' => $source->id,
        'quantity' => '25.0000', 'reserved_quantity' => '0.0000', 'low_stock_threshold' => '0',
    ]);

    Notification::fake();
    $transfer = app(InventoryTransferService::class)->dispatch([
        'product_id' => $product->id,
        'source_location_id' => $source->id,
        'destination_location_id' => $destination->id,
        'quantity' => '10',
        'idempotency_key' => (string) Str::uuid(),
    ], $sender);

    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Simulated receipt notification storage failure.'));

    expect(fn () => app(InventoryTransferService::class)->receive($transfer, [
        'quantity_received' => '10',
        'idempotency_key' => (string) Str::uuid(),
    ], $receiver))->toThrow(RuntimeException::class, 'Simulated receipt notification storage failure.');

    $this->assertDatabaseHas('inventory_transfers', ['id' => $transfer->id, 'status' => 'in_transit', 'quantity_received' => '0.0000']);
    $this->assertDatabaseMissing('inventory_stocks', ['location_id' => $destination->id, 'product_id' => $product->id]);
    $this->assertDatabaseMissing('inventory_transfer_receipts', ['inventory_transfer_id' => $transfer->id]);
    $this->assertDatabaseMissing('stock_movements', ['transfer_group_id' => $transfer->transfer_uuid, 'movement_type' => 'transfer_in']);
});

test('discrepancy notification failure rolls back discrepancy status and audit metadata atomically', function () {
    $sender = createTransferWorkflowUser(['system.inventory.transfer']);
    $receiver = createTransferWorkflowUser(['system.inventory.transfer']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();
    $sender->locations()->attach($source->id, ['access_level' => 'manage']);
    $receiver->locations()->attach($destination->id, ['access_level' => 'receive']);
    InventoryStock::query()->create([
        'product_id' => $product->id, 'location_id' => $source->id,
        'quantity' => '25.0000', 'reserved_quantity' => '0.0000', 'low_stock_threshold' => '0',
    ]);

    Notification::fake();
    $transfer = app(InventoryTransferService::class)->dispatch([
        'product_id' => $product->id,
        'source_location_id' => $source->id,
        'destination_location_id' => $destination->id,
        'quantity' => '10',
        'idempotency_key' => (string) Str::uuid(),
    ], $sender);

    Notification::shouldReceive('send')->once()->andThrow(new RuntimeException('Simulated discrepancy notification storage failure.'));

    expect(fn () => app(InventoryTransferService::class)->reject(
        $transfer,
        'Test discrepancy reason',
        $receiver,
    ))->toThrow(RuntimeException::class, 'Simulated discrepancy notification storage failure.');

    $this->assertDatabaseHas('inventory_transfers', ['id' => $transfer->id, 'status' => 'in_transit']);
    expect(InventoryTransfer::query()->findOrFail($transfer->id)->metadata['discrepancy'] ?? null)->toBeNull();
});

test('transfer counts do not reveal discrepancies to view-only location assignments', function () {
    Notification::fake();

    $sender = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    $receiver = createTransferWorkflowUser(['system.inventory.transfer', 'system.inventory.view']);
    $viewer = createTransferWorkflowUser(['system.inventory.view']);
    $product = inventoryTestProduct();
    $source = inventoryTestLocation();
    $destination = inventoryTestLocation();

    $sender->locations()->attach($source->id, ['access_level' => 'manage']);
    $receiver->locations()->attach($destination->id, ['access_level' => 'receive']);
    $viewer->locations()->attach($source->id, ['access_level' => 'view']);

    InventoryStock::query()->create([
        'product_id' => $product->id,
        'location_id' => $source->id,
        'quantity' => '20.0000',
        'reserved_quantity' => '0.0000',
        'low_stock_threshold' => '0',
    ]);

    $this->actingAs($sender)->postJson('/api/inventory/transfers', [
        'product_id' => $product->id,
        'source_location_id' => $source->id,
        'destination_location_id' => $destination->id,
        'quantity' => '5',
        'reason' => 'Authorization matrix regression',
        'idempotency_key' => (string) Str::uuid(),
    ])->assertCreated();

    $transfer = InventoryTransfer::query()->firstOrFail();

    $this->actingAs($receiver)->postJson("/api/inventory/transfers/{$transfer->id}/reject", [
        'reason' => 'Received quantity does not match',
    ])->assertOk();

    $this->actingAs($viewer)->getJson('/api/inventory/transfers/counts')
        ->assertOk()
        ->assertJsonPath('incoming_pending', 0)
        ->assertJsonPath('outgoing_pending', 0)
        ->assertJsonPath('discrepancies', 0);
});
