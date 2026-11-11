<?php

use App\Models\FinancialAccount;
use App\Models\GoodsReceipt;
use App\Models\InventoryStock;
use App\Models\JournalEntryLine;
use App\Models\Module;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\PurchaseRequisition;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Services\ProcurementService;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->requester = User::factory()->create();
    $this->approver = User::factory()->create();
    $this->location = inventoryTestLocation('proc-'.strtolower(Str::random(6)));
    $this->product = inventoryTestProduct();

    $this->supplier = Supplier::query()->create([
        'supplier_code' => 'SUP-'.strtoupper(Str::random(8)),
        'name' => 'Test Supplier',
        'status' => 'active',
        'created_by' => $this->requester->id,
    ]);

    $this->service = app(ProcurementService::class);
});

function createProcurementApiUser(array $permissionSlugs): User
{
    $user = User::factory()->create();
    $module = Module::query()->firstOrCreate(['slug' => 'procurement'], [
        'name' => 'Procurement & Suppliers',
        'description' => 'Procurement authorization test module.',
        'sort_order' => 75,
        'is_system' => true,
        'is_active' => true,
    ]);
    if (! $module->is_active) {
        $module->update(['is_active' => true]);
    }

    $role = Role::query()->create([
        'name' => 'Procurement API Test Role',
        'slug' => 'procurement-api-test-'.strtolower(Str::random(10)),
        'description' => 'Temporary role for procurement authorization tests.',
        'is_system' => false,
        'is_active' => true,
    ]);

    foreach ($permissionSlugs as $slug) {
        $permission = Permission::query()->firstOrCreate(['slug' => $slug], [
            'module_id' => $module->id,
            'name' => ucfirst(str_replace(['system.procurement.', '.', '_'], ['', ' ', ' '], $slug)),
            'description' => 'Procurement authorization test permission.',
        ]);
        $role->permissions()->attach($permission->id);
    }

    $user->roles()->attach($role->id);

    return $user;
}

function createApprovedPurchaseOrder(object $test, string $quantity = '10'): PurchaseOrder
{
    $po = $test->service->createPurchaseOrder([
        'supplier_id' => $test->supplier->id,
        'location_id' => $test->location->id,
        'order_date' => now()->toDateString(),
        'items' => [[
            'product_id' => $test->product->id,
            'quantity' => $quantity,
            'unit_cost' => '100.00',
        ]],
    ], $test->requester);

    return $test->service->approvePurchaseOrder($po, $test->approver);
}

it('completes requisition, purchase order, partial goods receipt, invoice, payment and supplier return atomically', function (): void {
    $requisition = $this->service->createRequisition([
        'location_id' => $this->location->id,
        'purpose' => 'Replenish branch stock',
        'items' => [['product_id' => $this->product->id, 'quantity' => '10']],
    ], $this->requester);

    $approvedRequisition = $this->service->approveRequisition($requisition, $this->approver);
    expect($approvedRequisition->status)->toBe('approved');

    $po = $this->service->createPurchaseOrder([
        'supplier_id' => $this->supplier->id,
        'location_id' => $this->location->id,
        'purchase_requisition_id' => $requisition->id,
        'order_date' => now()->toDateString(),
        'items' => [['product_id' => $this->product->id, 'quantity' => '10', 'unit_cost' => '100.00']],
    ], $this->requester);
    $po = $this->service->approvePurchaseOrder($po, $this->approver);

    $receiptPayload = [
        'idempotency_key' => (string) Str::uuid(),
        'received_date' => now()->toDateString(),
        'items' => [[
            'purchase_order_item_id' => $po->items->first()->id,
            'received_quantity' => '7',
            'accepted_quantity' => '6',
            'rejected_quantity' => '1',
            'rejection_reason' => 'Damaged packaging',
        ]],
    ];
    $firstReceipt = $this->service->receiveGoods($po, $receiptPayload, $this->approver);
    expect($firstReceipt->journalEntry?->status)->toBe('posted');
    $replayedReceipt = $this->service->receiveGoods($po, $receiptPayload, $this->approver);
    expect($replayedReceipt->id)->toBe($firstReceipt->id);
    expect(fn () => $this->service->receiveGoods($po, array_replace($receiptPayload, [
        'items' => [[
            'purchase_order_item_id' => $po->items->first()->id,
            'received_quantity' => '7',
            'accepted_quantity' => '7',
            'rejected_quantity' => '0',
        ]],
    ]), $this->approver))->toThrow(ValidationException::class);
    expect(GoodsReceipt::query()->where('idempotency_key', $receiptPayload['idempotency_key'])->count())->toBe(1);

    $this->service->receiveGoods($po->fresh(), [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $po->items->first()->id,
            'received_quantity' => '3',
            'accepted_quantity' => '3',
            'rejected_quantity' => '0',
        ]],
    ], $this->approver);
    $this->service->receiveGoods($po->fresh(), [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $po->items->first()->id,
            'received_quantity' => '1',
            'accepted_quantity' => '1',
            'rejected_quantity' => '0',
        ]],
    ], $this->approver);

    $poItem = $po->items()->firstOrFail()->fresh();
    expect($poItem->received_quantity)->toBe('11.0000')
        ->and($poItem->accepted_quantity)->toBe('10.0000')
        ->and($po->fresh()->status)->toBe('received');

    $stock = InventoryStock::query()->where('location_id', $this->location->id)->where('product_id', $this->product->id)->firstOrFail();
    expect($stock->quantity)->toBe('10.0000');

    $invoice = $this->service->createSupplierInvoice($po, [
        'invoice_number' => 'INV-'.strtoupper(Str::random(8)),
        'invoice_date' => now()->toDateString(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'quantity' => '10',
            'unit_cost' => '100.00',
        ]],
        'tax_total' => '0',
        'discount_total' => '0',
    ], $this->requester);
    $invoice = $this->service->postSupplierInvoice($invoice, $this->approver);
    expect($invoice->status)->toBe('posted')
        ->and($invoice->journalEntry->status)->toBe('posted');

    $grni = FinancialAccount::query()->where('code', '2050')->firstOrFail();
    $grniNet = (string) JournalEntryLine::query()
        ->where('account_id', $grni->id)
        ->whereHas('journalEntry', fn ($query) => $query->where('status', 'posted'))
        ->selectRaw('COALESCE(SUM(credit - debit), 0) AS balance')
        ->value('balance');
    expect(bccomp($grniNet, '0.00', 2))->toBe(0);

    $cash = FinancialAccount::query()->where('code', '1000')->firstOrFail();
    $paymentPayload = [
        'payment_date' => now()->toDateString(),
        'amount' => '300.00',
        'payment_method' => 'cash',
        'cash_account_id' => $cash->id,
        'idempotency_key' => (string) Str::uuid(),
    ];
    $payment = $this->service->paySupplierInvoice($invoice, $paymentPayload, $this->approver);
    $replay = $this->service->paySupplierInvoice($invoice->fresh(), $paymentPayload, $this->approver);
    expect($replay->id)->toBe($payment->id)
        ->and($invoice->fresh()->status)->toBe('partially_paid')
        ->and($invoice->fresh()->paid_total)->toBe('300.00');
    expect(fn () => $this->service->paySupplierInvoice($invoice->fresh(), array_replace($paymentPayload, [
        'amount' => '350.00',
    ]), $this->approver))->toThrow(ValidationException::class);
    expect(SupplierPayment::query()->where('idempotency_key', $paymentPayload['idempotency_key'])->count())->toBe(1)
        ->and($invoice->fresh()->paid_total)->toBe('300.00');

    $returnPayload = [
        'return_date' => now()->toDateString(),
        'reason' => 'Quality issue confirmed after inspection',
        'idempotency_key' => (string) Str::uuid(),
        'items' => [['purchase_order_item_id' => $poItem->id, 'quantity' => '2']],
    ];
    $supplierReturn = $this->service->returnToSupplier($invoice->fresh(), $returnPayload, $this->approver);
    $returnReplay = $this->service->returnToSupplier($invoice->fresh(), $returnPayload, $this->approver);

    expect($supplierReturn->total_amount)->toBe('200.00')
        ->and($returnReplay->id)->toBe($supplierReturn->id)
        ->and($stock->fresh()->quantity)->toBe('8.0000')
        ->and($supplierReturn->journalEntry->status)->toBe('posted');

    expect(fn () => $this->service->returnToSupplier($invoice->fresh(), [
        'return_date' => now()->toDateString(),
        'reason' => 'Attempt to return more than invoiced quantity',
        'idempotency_key' => (string) Str::uuid(),
        'items' => [['purchase_order_item_id' => $poItem->id, 'quantity' => '9']],
    ], $this->approver))->toThrow(ValidationException::class);

    $payable = FinancialAccount::query()->where('code', '2000')->firstOrFail();
    $supplierPayable = (string) JournalEntryLine::query()
        ->where('account_id', $payable->id)
        ->where('supplier_id', $this->supplier->id)
        ->whereHas('journalEntry', fn ($query) => $query->where('status', 'posted'))
        ->selectRaw('COALESCE(SUM(credit - debit), 0) AS balance')
        ->value('balance');
    expect(bccomp($supplierPayable, '500.00', 2))->toBe(0);
});

it('prevents a requester or purchase order creator from approving their own documents', function (): void {
    $requisition = $this->service->createRequisition([
        'location_id' => $this->location->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => '2']],
    ], $this->requester);

    expect(fn () => $this->service->approveRequisition($requisition, $this->requester))
        ->toThrow(ValidationException::class);

    $po = $this->service->createPurchaseOrder([
        'supplier_id' => $this->supplier->id,
        'location_id' => $this->location->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => '2', 'unit_cost' => '10.00']],
    ], $this->requester);

    expect(fn () => $this->service->approvePurchaseOrder($po, $this->requester))
        ->toThrow(ValidationException::class);
});

it('rejects over-receipt and supplier invoices above accepted goods value', function (): void {
    $po = createApprovedPurchaseOrder($this, '5');
    $poItem = $po->items->firstOrFail();

    expect(fn () => $this->service->receiveGoods($po, [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'received_quantity' => '6',
            'accepted_quantity' => '6',
            'rejected_quantity' => '0',
        ]],
    ], $this->approver))->toThrow(ValidationException::class);

    $this->service->receiveGoods($po, [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'received_quantity' => '5',
            'accepted_quantity' => '3',
            'rejected_quantity' => '2',
        ]],
    ], $this->approver);

    expect(fn () => $this->service->createSupplierInvoice($po, [
        'invoice_number' => 'OVER-'.strtoupper(Str::random(6)),
        'invoice_date' => now()->toDateString(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'quantity' => '4',
            'unit_cost' => '100.00',
        ]],
    ], $this->requester))->toThrow(ValidationException::class);
});

it('rejects zero-quantity requisition and purchase order lines before database writes', function (): void {
    expect(fn () => $this->service->createRequisition([
        'location_id' => $this->location->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => '0']],
    ], $this->requester))->toThrow(ValidationException::class);

    expect(fn () => $this->service->createPurchaseOrder([
        'supplier_id' => $this->supplier->id,
        'location_id' => $this->location->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => '0', 'unit_cost' => '10.00']],
    ], $this->requester))->toThrow(ValidationException::class);
});

it('blocks procurement creation against a location the actor cannot manage', function (): void {
    $otherLocation = inventoryTestLocation('proc-other-'.strtolower(Str::random(6)));
    $actor = createProcurementApiUser([
        'system.procurement.requisitions.create',
        'system.procurement.orders.create',
    ]);
    $actor->locations()->attach($this->location->id, ['access_level' => 'manage']);

    $this->actingAs($actor);

    $this->postJson('/api/v1/backend/procurement/requisitions', [
        'location_id' => $otherLocation->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => '2']],
    ])->assertForbidden();

    $this->postJson('/api/v1/backend/procurement/orders', [
        'supplier_id' => $this->supplier->id,
        'location_id' => $otherLocation->id,
        'order_date' => now()->toDateString(),
        'items' => [['product_id' => $this->product->id, 'quantity' => '2', 'unit_cost' => '10.00']],
    ])->assertForbidden();

    expect(PurchaseRequisition::query()->count())->toBe(0)
        ->and(PurchaseOrder::query()->count())->toBe(0);
});

it('blocks cross-location requisition and purchase order approvals without changing document state', function (): void {
    $requisition = $this->service->createRequisition([
        'location_id' => $this->location->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => '2']],
    ], $this->requester);
    $purchaseOrder = $this->service->createPurchaseOrder([
        'supplier_id' => $this->supplier->id,
        'location_id' => $this->location->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => '2', 'unit_cost' => '10.00']],
    ], $this->requester);

    $actor = createProcurementApiUser([
        'system.procurement.requisitions.approve',
        'system.procurement.orders.approve',
    ]);
    $otherLocation = inventoryTestLocation('proc-other-'.strtolower(Str::random(6)));
    $actor->locations()->attach($otherLocation->id, ['access_level' => 'manage']);

    $this->actingAs($actor);

    $this->postJson('/api/v1/backend/procurement/requisitions/'.$requisition->id.'/approve')->assertForbidden();
    $this->postJson('/api/v1/backend/procurement/orders/'.$purchaseOrder->id.'/approve')->assertForbidden();

    expect($requisition->fresh()->status)->toBe('draft')
        ->and($purchaseOrder->fresh()->status)->toBe('draft')
        ->and($requisition->fresh()->approved_by)->toBeNull()
        ->and($purchaseOrder->fresh()->approved_by)->toBeNull();
});

it('blocks cross-location goods receiving and supplier invoice creation', function (): void {
    $purchaseOrder = createApprovedPurchaseOrder($this);
    $actor = createProcurementApiUser([
        'system.procurement.receiving.manage',
        'system.procurement.invoices.manage',
    ]);
    $otherLocation = inventoryTestLocation('proc-other-'.strtolower(Str::random(6)));
    $actor->locations()->attach($otherLocation->id, ['access_level' => 'manage']);

    $this->actingAs($actor);

    $this->postJson('/api/v1/backend/procurement/orders/'.$purchaseOrder->id.'/receive', [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [],
    ])->assertForbidden();

    $this->postJson('/api/v1/backend/procurement/orders/'.$purchaseOrder->id.'/invoices', [
        'invoice_number' => 'CROSS-'.strtoupper(Str::random(8)),
        'invoice_date' => now()->toDateString(),
        'items' => [],
    ])->assertForbidden();

    expect(GoodsReceipt::query()->count())->toBe(0)
        ->and($purchaseOrder->fresh()->status)->toBe('approved')
        ->and(SupplierInvoice::query()->count())->toBe(0);
});

it('blocks cross-location invoice posting, supplier payments and supplier returns', function (): void {
    $purchaseOrder = createApprovedPurchaseOrder($this, '2');
    $poItem = $purchaseOrder->items->firstOrFail();
    $this->service->receiveGoods($purchaseOrder, [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'received_quantity' => '2',
            'accepted_quantity' => '2',
            'rejected_quantity' => '0',
        ]],
    ], $this->approver);
    $invoice = $this->service->createSupplierInvoice($purchaseOrder->fresh(), [
        'invoice_number' => 'CROSS-'.strtoupper(Str::random(8)),
        'invoice_date' => now()->toDateString(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'quantity' => '2',
            'unit_cost' => '100.00',
        ]],
    ], $this->requester);

    $actor = createProcurementApiUser([
        'system.procurement.invoices.manage',
        'system.procurement.payments.manage',
        'system.procurement.returns.manage',
    ]);
    $otherLocation = inventoryTestLocation('proc-other-'.strtolower(Str::random(6)));
    $actor->locations()->attach($otherLocation->id, ['access_level' => 'manage']);

    $this->actingAs($actor);

    $this->postJson('/api/v1/backend/procurement/invoices/'.$invoice->id.'/post')->assertForbidden();
    $this->postJson('/api/v1/backend/procurement/invoices/'.$invoice->id.'/payments', [
        'payment_date' => now()->toDateString(),
        'amount' => '10.00',
        'payment_method' => 'cash',
        'cash_account_id' => FinancialAccount::query()->where('code', '1000')->value('id'),
        'idempotency_key' => (string) Str::uuid(),
    ])->assertForbidden();
    $this->postJson('/api/v1/backend/procurement/invoices/'.$invoice->id.'/returns', [
        'reason' => 'Cross-location authorization test',
        'idempotency_key' => (string) Str::uuid(),
        'items' => [['purchase_order_item_id' => $poItem->id, 'quantity' => '1']],
    ])->assertForbidden();

    expect($invoice->fresh()->status)->toBe('draft')
        ->and(SupplierPayment::query()->count())->toBe(0)
        ->and(SupplierReturn::query()->count())->toBe(0);
});

it('limits procurement overview operational records to assigned locations while retaining shared supplier master data', function (): void {
    $otherLocation = inventoryTestLocation('proc-other-'.strtolower(Str::random(6)));
    $otherRequisition = $this->service->createRequisition([
        'location_id' => $otherLocation->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => '1']],
    ], $this->requester);
    $visibleRequisition = $this->service->createRequisition([
        'location_id' => $this->location->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => '1']],
    ], $this->requester);

    $actor = createProcurementApiUser(['system.procurement.view']);
    $actor->locations()->attach($this->location->id, ['access_level' => 'view']);

    $response = $this->actingAs($actor)
        ->getJson('/api/v1/backend/procurement/overview')
        ->assertOk();

    $requisitionIds = collect($response->json('requisitions'))
        ->pluck('id')
        ->map(fn ($id): int => (int) $id);

    expect($requisitionIds)->toContain($visibleRequisition->id)
        ->not->toContain($otherRequisition->id);

    expect(collect($response->json('suppliers'))->pluck('id')->map(fn ($id): int => (int) $id))
        ->toContain($this->supplier->id);
});

it('does not allow view-only location access to approve procurement documents', function (): void {
    $purchaseOrder = $this->service->createPurchaseOrder([
        'supplier_id' => $this->supplier->id,
        'location_id' => $this->location->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => '2', 'unit_cost' => '10.00']],
    ], $this->requester);

    $actor = createProcurementApiUser(['system.procurement.orders.approve']);
    $actor->locations()->attach($this->location->id, ['access_level' => 'view']);

    $this->actingAs($actor)
        ->postJson('/api/v1/backend/procurement/orders/'.$purchaseOrder->id.'/approve')
        ->assertForbidden();

    expect($purchaseOrder->fresh()->status)->toBe('draft')
        ->and($purchaseOrder->fresh()->approved_by)->toBeNull();
});
