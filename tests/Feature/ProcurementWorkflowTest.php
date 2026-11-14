<?php

use App\Models\FinancialAccount;
use App\Models\GoodsReceipt;
use App\Models\InventoryStock;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Module;
use App\Models\Permission;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderRevision;
use App\Models\PurchaseRequisition;
use App\Models\Role;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierReturn;
use App\Models\User;
use App\Services\FinancialLedgerService;
use App\Services\ProcurementService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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

it('rolls back stock, receipt and journal writes when GRN accounting posting fails', function (): void {
    $po = createApprovedPurchaseOrder($this, '5');
    $poItem = $po->items->firstOrFail();

    $ledger = Mockery::mock(app(FinancialLedgerService::class))->makePartial();
    $ledger->shouldReceive('post')->once()->andThrow(new RuntimeException('Simulated ledger posting failure.'));
    app()->instance(FinancialLedgerService::class, $ledger);
    $this->service = app(ProcurementService::class);

    expect(fn () => $this->service->receiveGoods($po, [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'received_quantity' => '5',
            'accepted_quantity' => '5',
            'rejected_quantity' => '0',
        ]],
    ], $this->approver))->toThrow(RuntimeException::class);

    expect(GoodsReceipt::query()->where('purchase_order_id', $po->id)->count())->toBe(0)
        ->and(StockMovement::query()->where('reference_type', 'goods_receipt')->count())->toBe(0)
        ->and(InventoryStock::query()->where('location_id', $this->location->id)->where('product_id', $this->product->id)->exists())->toBeFalse()
        ->and(JournalEntry::query()->where('source', 'procurement_grn')->count())->toBe(0)
        ->and($poItem->fresh()->received_quantity)->toBe('0.0000')
        ->and($poItem->fresh()->accepted_quantity)->toBe('0.0000')
        ->and($po->fresh()->status)->toBe('approved');
});

it('keeps a supplier invoice draft and removes its journal draft when invoice posting fails', function (): void {
    $po = createApprovedPurchaseOrder($this, '5');
    $poItem = $po->items->firstOrFail();
    $this->service->receiveGoods($po, [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'received_quantity' => '5',
            'accepted_quantity' => '5',
            'rejected_quantity' => '0',
        ]],
    ], $this->approver);

    $invoice = $this->service->createSupplierInvoice($po->fresh(), [
        'invoice_number' => 'ROLLBACK-'.strtoupper(Str::random(8)),
        'invoice_date' => now()->toDateString(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'quantity' => '5',
            'unit_cost' => '100.00',
        ]],
        'tax_total' => '0',
        'discount_total' => '0',
    ], $this->requester);

    $ledger = Mockery::mock(app(FinancialLedgerService::class))->makePartial();
    $ledger->shouldReceive('post')->once()->andThrow(new RuntimeException('Simulated ledger posting failure.'));
    app()->instance(FinancialLedgerService::class, $ledger);
    $this->service = app(ProcurementService::class);

    expect(fn () => $this->service->postSupplierInvoice($invoice, $this->approver))
        ->toThrow(RuntimeException::class);

    expect($invoice->fresh()->status)->toBe('draft')
        ->and($invoice->fresh()->journal_entry_id)->toBeNull()
        ->and(JournalEntry::query()->where('source', 'procurement_invoice')->count())->toBe(0);
});

it('prevents duplicate supplier invoice journal posting on repeated requests', function (): void {
    $po = createApprovedPurchaseOrder($this, '3');
    $poItem = $po->items->firstOrFail();
    $this->service->receiveGoods($po, [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'received_quantity' => '3',
            'accepted_quantity' => '3',
            'rejected_quantity' => '0',
        ]],
    ], $this->approver);

    $invoice = $this->service->createSupplierInvoice($po->fresh(), [
        'invoice_number' => 'POST-ONCE-'.strtoupper(Str::random(8)),
        'invoice_date' => now()->toDateString(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'quantity' => '3',
            'unit_cost' => '100.00',
        ]],
        'tax_total' => '0',
        'discount_total' => '0',
    ], $this->requester);

    $postedInvoice = $this->service->postSupplierInvoice($invoice, $this->approver);
    expect($postedInvoice->status)->toBe('posted')
        ->and($postedInvoice->journal_entry_id)->not->toBeNull();

    expect(fn () => $this->service->postSupplierInvoice($invoice, $this->approver))
        ->toThrow(ValidationException::class);

    expect(JournalEntry::query()->where('source', 'procurement_invoice')->count())->toBe(1)
        ->and($invoice->fresh()->status)->toBe('posted')
        ->and($invoice->fresh()->journal_entry_id)->toBe($postedInvoice->journal_entry_id);
});

it('prevents duplicate supplier invoice numbers and reconciles multiple partial invoices against accepted receipts', function (): void {
    $po = createApprovedPurchaseOrder($this, '10');
    $poItem = $po->items->firstOrFail();

    $this->service->receiveGoods($po, [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'received_quantity' => '4',
            'accepted_quantity' => '4',
            'rejected_quantity' => '0',
        ]],
    ], $this->approver);

    $firstInvoice = $this->service->createSupplierInvoice($po->fresh(), [
        'invoice_number' => 'SUP-INV-1001',
        'invoice_date' => now()->toDateString(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'quantity' => '4',
            'unit_cost' => '100.00',
        ]],
        'tax_total' => '0',
        'discount_total' => '0',
    ], $this->requester);

    expect($firstInvoice->grand_total)->toBe('400.00');
    expect(fn () => $this->service->createSupplierInvoice($po->fresh(), [
        'invoice_number' => 'SUP-INV-1001',
        'invoice_date' => now()->toDateString(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'quantity' => '1',
            'unit_cost' => '100.00',
        ]],
        'tax_total' => '0',
        'discount_total' => '0',
    ], $this->requester))->toThrow(ValidationException::class);

    $this->service->receiveGoods($po->fresh(), [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'received_quantity' => '6',
            'accepted_quantity' => '6',
            'rejected_quantity' => '0',
        ]],
    ], $this->approver);

    $secondInvoice = $this->service->createSupplierInvoice($po->fresh(), [
        'invoice_number' => 'SUP-INV-1002',
        'invoice_date' => now()->toDateString(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'quantity' => '6',
            'unit_cost' => '100.00',
        ]],
        'tax_total' => '0',
        'discount_total' => '0',
    ], $this->requester);

    expect($secondInvoice->grand_total)->toBe('600.00')
        ->and(SupplierInvoice::query()->where('supplier_id', $this->supplier->id)->count())->toBe(2);

    expect(fn () => $this->service->createSupplierInvoice($po->fresh(), [
        'invoice_number' => 'SUP-INV-1003',
        'invoice_date' => now()->toDateString(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'quantity' => '0.01',
            'unit_cost' => '100.00',
        ]],
        'tax_total' => '0',
        'discount_total' => '0',
    ], $this->requester))->toThrow(ValidationException::class);
});

it('reconciles supplier invoice tax and discount components separately against partial PO quantities', function (): void {
    $po = $this->service->createPurchaseOrder([
        'supplier_id' => $this->supplier->id,
        'location_id' => $this->location->id,
        'order_date' => now()->toDateString(),
        'items' => [[
            'product_id' => $this->product->id,
            'quantity' => '10',
            'unit_cost' => '100.00',
            'discount_amount' => '20.00',
            'tax_amount' => '10.00',
        ]],
    ], $this->requester);
    $po = $this->service->approvePurchaseOrder($po, $this->approver);
    $poItem = $po->items->firstOrFail();

    $this->service->receiveGoods($po, [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'received_quantity' => '5',
            'accepted_quantity' => '5',
            'rejected_quantity' => '0',
        ]],
    ], $this->approver);

    $invoicePayload = [
        'invoice_date' => now()->toDateString(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'quantity' => '5',
            'unit_cost' => '100.00',
        ]],
    ];

    expect(fn () => $this->service->createSupplierInvoice($po->fresh(), array_merge($invoicePayload, [
        'invoice_number' => 'TAX-MISMATCH-'.strtoupper(Str::random(6)),
        'tax_total' => '0.00',
        'discount_total' => '5.00',
    ]), $this->requester))->toThrow(ValidationException::class);

    $invoice = $this->service->createSupplierInvoice($po->fresh(), array_merge($invoicePayload, [
        'invoice_number' => 'TAX-MATCH-'.strtoupper(Str::random(6)),
        'tax_total' => '5.00',
        'discount_total' => '10.00',
    ]), $this->requester);

    expect($invoice->subtotal)->toBe('500.00')
        ->and($invoice->discount_total)->toBe('10.00')
        ->and($invoice->tax_total)->toBe('5.00')
        ->and($invoice->grand_total)->toBe('495.00');
});

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
            'received_quantity' => '6',
            'accepted_quantity' => '6',
            'rejected_quantity' => '0',
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
    expect($poItem->received_quantity)->toBe('10.0000')
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

    expect(fn () => $this->service->paySupplierInvoice($invoice->fresh(), array_replace($paymentPayload, [
        'amount' => '501.00',
        'idempotency_key' => (string) Str::uuid(),
    ]), $this->approver))->toThrow(ValidationException::class);

    $netBalancePayment = $this->service->paySupplierInvoice($invoice->fresh(), array_replace($paymentPayload, [
        'amount' => '500.00',
        'idempotency_key' => (string) Str::uuid(),
    ]), $this->approver);
    expect($netBalancePayment->amount)->toBe('500.00')
        ->and($invoice->fresh()->paid_total)->toBe('800.00')
        ->and($invoice->fresh()->status)->toBe('paid');

    $payable = FinancialAccount::query()->where('code', '2000')->firstOrFail();
    $supplierPayable = (string) JournalEntryLine::query()
        ->where('account_id', $payable->id)
        ->where('supplier_id', $this->supplier->id)
        ->whereHas('journalEntry', fn ($query) => $query->where('status', 'posted'))
        ->selectRaw('COALESCE(SUM(credit - debit), 0) AS balance')
        ->value('balance');
    expect(bccomp($supplierPayable, '0.00', 2))->toBe(0);
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

it('prevents approval of a purchase order when its supplier is inactive or blocked', function (): void {
    $po = $this->service->createPurchaseOrder([
        'supplier_id' => $this->supplier->id,
        'location_id' => $this->location->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => '2', 'unit_cost' => '10.00']],
    ], $this->requester);
    $this->supplier->update(['status' => 'blocked']);

    expect(fn () => $this->service->approvePurchaseOrder($po, $this->approver))
        ->toThrow(ValidationException::class);
    expect($po->fresh()->status)->toBe('draft');
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
            'rejection_reason' => 'Two units arrived damaged',
        ]],
    ], $this->approver);

    // Physical delivery is complete even though only accepted units are invoiceable.
    expect($po->fresh()->status)->toBe('received');

    $acceptedInvoice = $this->service->createSupplierInvoice($po->fresh(), [
        'invoice_number' => 'ACCEPTED-'.strtoupper(Str::random(6)),
        'invoice_date' => now()->toDateString(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'quantity' => '3',
            'unit_cost' => '100.00',
        ]],
    ], $this->requester);
    expect($acceptedInvoice->items->firstOrFail()->quantity)->toBe('3.0000');

    expect(fn () => $this->service->receiveGoods($po, [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'received_quantity' => '1',
            'accepted_quantity' => '0',
            'rejected_quantity' => '1',
            'rejection_reason' => 'Damaged packaging',
        ]],
    ], $this->approver))->toThrow(ValidationException::class);

    expect(fn () => $this->service->receiveGoods($po, [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'received_quantity' => '1',
            'accepted_quantity' => '0',
            'rejected_quantity' => '1',
        ]],
    ], $this->approver))->toThrow(ValidationException::class);

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

it('requires rejection reasons and caps rejected quantities within the ordered balance', function (): void {
    $po = createApprovedPurchaseOrder($this, '5');
    $poItem = $po->items->firstOrFail();

    expect(fn () => $this->service->receiveGoods($po, [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'received_quantity' => '1',
            'accepted_quantity' => '0',
            'rejected_quantity' => '1',
        ]],
    ], $this->approver))->toThrow(ValidationException::class);

    expect(fn () => $this->service->receiveGoods($po, [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $poItem->id,
            'received_quantity' => '6',
            'accepted_quantity' => '0',
            'rejected_quantity' => '6',
            'rejection_reason' => 'Damaged packaging',
        ]],
    ], $this->approver))->toThrow(ValidationException::class);
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

it('manages supplier profile contacts with a single primary contact', function (): void {
    $actor = createProcurementApiUser(['system.procurement.view', 'system.procurement.suppliers.manage']);
    $this->actingAs($actor);

    $profile = $this->getJson('/api/v1/backend/procurement/suppliers/'.$this->supplier->id)
        ->assertOk()
        ->assertJsonPath('supplier.id', $this->supplier->id)
        ->assertJsonStructure(['supplier' => ['contacts', 'documents', 'purchase_orders_count', 'invoices_count', 'purchase_orders', 'activity']]);

    $first = $this->postJson('/api/v1/backend/procurement/suppliers/'.$this->supplier->id.'/contacts', [
        'name' => 'Accounts Manager',
        'designation' => 'Accounts',
        'email' => 'accounts@example.test',
        'is_primary' => true,
    ])->assertCreated()->assertJsonPath('contact.is_primary', true);

    $firstId = (int) $first->json('contact.id');
    $second = $this->postJson('/api/v1/backend/procurement/suppliers/'.$this->supplier->id.'/contacts', [
        'name' => 'Procurement Officer',
        'designation' => 'Procurement',
        'phone' => '+8801700000000',
        'is_primary' => true,
    ])->assertCreated()->assertJsonPath('contact.is_primary', true);

    expect($this->supplier->contacts()->where('is_primary', true)->count())->toBe(1)
        ->and($this->supplier->contacts()->whereKey($firstId)->firstOrFail()->is_primary)->toBeFalse();

    $this->deleteJson('/api/v1/backend/procurement/suppliers/'.$this->supplier->id.'/contacts/'.$second->json('contact.id'))
        ->assertOk();

    $this->get('/api/v1/backend/procurement/suppliers/export')->assertOk()
        ->assertHeader('content-type', 'text/csv; charset=UTF-8');
});

it('stores supplier documents privately and enforces supplier ownership on download and deletion', function (): void {
    Storage::fake('local');
    $actor = createProcurementApiUser(['system.procurement.view', 'system.procurement.suppliers.manage']);
    $this->actingAs($actor);

    $upload = $this->post('/api/v1/backend/procurement/suppliers/'.$this->supplier->id.'/documents', [
        'file' => UploadedFile::fake()->create('trade-license.pdf', 120, 'application/pdf'),
        'category' => 'trade_license',
        'expires_at' => now()->addYear()->toDateString(),
    ])->assertCreated();

    $documentId = (int) $upload->json('document.id');
    expect($upload->json('document'))->not->toHaveKey('path')
        ->and($upload->json('document'))->not->toHaveKey('disk');

    $document = $this->supplier->documents()->findOrFail($documentId);
    expect($document->disk)->toBe('local')
        ->and(str_starts_with($document->path, 'procurement/suppliers/'.$this->supplier->id.'/'))->toBeTrue();
    Storage::disk('local')->assertExists($document->path);

    $otherSupplier = Supplier::query()->create([
        'supplier_code' => 'SUP-'.strtoupper(Str::random(8)),
        'name' => 'Other Supplier',
        'status' => 'active',
        'created_by' => $this->requester->id,
    ]);

    $this->get('/api/v1/backend/procurement/suppliers/'.$otherSupplier->id.'/documents/'.$documentId.'/download')
        ->assertNotFound();

    $this->get('/api/v1/backend/procurement/suppliers/'.$this->supplier->id.'/documents/'.$documentId.'/download')
        ->assertOk();

    $this->deleteJson('/api/v1/backend/procurement/suppliers/'.$this->supplier->id.'/documents/'.$documentId)
        ->assertOk();
    Storage::disk('local')->assertMissing($document->path);
});

it('denies supplier profile and document access without procurement permissions', function (): void {
    $actor = createProcurementApiUser([]);
    $this->actingAs($actor)
        ->getJson('/api/v1/backend/procurement/suppliers/'.$this->supplier->id)
        ->assertForbidden();
});

it('supports maker-checker submission and rejection for requisitions', function (): void {
    $requisition = $this->service->createRequisition([
        'location_id' => $this->location->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => '3']],
    ], $this->requester);

    $submitted = $this->service->submitRequisition($requisition, $this->requester);
    expect($submitted->status)->toBe('submitted')
        ->and($submitted->submitted_by)->toBe($this->requester->id);

    expect(fn () => $this->service->rejectRequisition($submitted, 'Requester cannot reject own request', $this->requester))
        ->toThrow(ValidationException::class);

    $rejected = $this->service->rejectRequisition($submitted, 'Quantity needs review', $this->approver);
    expect($rejected->status)->toBe('rejected')
        ->and($rejected->rejection_reason)->toBe('Quantity needs review');

    $resubmitted = $this->service->submitRequisition($rejected, $this->requester);
    expect($resubmitted->status)->toBe('submitted')
        ->and($resubmitted->rejection_reason)->toBeNull();

    $approved = $this->service->approveRequisition($resubmitted, $this->approver);
    expect($approved->status)->toBe('approved');
});

it('supports maker-checker submission and rejection for purchase orders', function (): void {
    $po = $this->service->createPurchaseOrder([
        'supplier_id' => $this->supplier->id,
        'location_id' => $this->location->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => '2', 'unit_cost' => '20.00']],
    ], $this->requester);

    $submitted = $this->service->submitPurchaseOrder($po, $this->requester);
    expect($submitted->status)->toBe('submitted');
    expect(fn () => $this->service->rejectPurchaseOrder($submitted, 'Self rejection', $this->requester))
        ->toThrow(ValidationException::class);

    $rejected = $this->service->rejectPurchaseOrder($submitted, 'Please revise price quote', $this->approver);
    expect($rejected->status)->toBe('rejected')
        ->and($rejected->rejection_reason)->toBe('Please revise price quote');

    $resubmitted = $this->service->submitPurchaseOrder($rejected, $this->requester);
    $approved = $this->service->approvePurchaseOrder($resubmitted, $this->approver);
    expect($approved->status)->toBe('approved')
        ->and($approved->approved_by)->toBe($this->approver->id);
});

it('allows creators to cancel only draft or rejected procurement documents', function (): void {
    $requisition = $this->service->createRequisition([
        'location_id' => $this->location->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => '1']],
    ], $this->requester);
    expect($this->service->cancelRequisition($requisition, $this->requester)->status)->toBe('cancelled');

    $po = $this->service->createPurchaseOrder([
        'supplier_id' => $this->supplier->id,
        'location_id' => $this->location->id,
        'items' => [['product_id' => $this->product->id, 'quantity' => '1', 'unit_cost' => '10.00']],
    ], $this->requester);
    expect($this->service->cancelPurchaseOrder($po, $this->requester)->status)->toBe('cancelled');
});

it('preserves approved purchase order revisions and applies them only after independent approval', function (): void {
    $po = createApprovedPurchaseOrder($this);
    $revision = $this->service->requestPurchaseOrderRevision($po, [
        'reason' => 'Supplier confirmed revised commercial pricing',
        'order_date' => now()->toDateString(),
        'expected_at' => now()->addDays(7)->toDateString(),
        'currency' => 'BDT',
        'notes' => 'Updated delivery commitment',
        'items' => [[
            'product_id' => $this->product->id,
            'quantity' => '12',
            'unit_cost' => '110.00',
            'discount_amount' => '0',
            'tax_amount' => '0',
        ]],
    ], $this->requester);

    expect($revision)->toBeInstanceOf(PurchaseOrderRevision::class)
        ->and($revision->status)->toBe('submitted')
        ->and($po->fresh()->grand_total)->toBe('1000.00');

    $approved = $this->service->approvePurchaseOrderRevision($revision, $this->approver);
    expect($approved->status)->toBe('approved')
        ->and($po->fresh()->revision_no)->toBe(2)
        ->and($po->fresh()->grand_total)->toBe('1320.00')
        ->and($po->fresh()->items()->firstOrFail()->quantity)->toBe('12.0000');

    $po = $po->fresh();
    $this->service->receiveGoods($po, [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $po->items()->firstOrFail()->id,
            'received_quantity' => '1',
            'accepted_quantity' => '1',
            'rejected_quantity' => '0',
        ]],
    ], $this->approver);

    expect(fn () => $this->service->requestPurchaseOrderRevision($po->fresh(), [
        'reason' => 'Attempt to revise after receiving started',
        'items' => [[
            'product_id' => $this->product->id,
            'quantity' => '12',
            'unit_cost' => '110.00',
        ]],
    ], $this->requester))->toThrow(ValidationException::class);
});

it('allocates GRNI rounding remainders consistently across partial receipts and invoices', function (): void {
    $purchaseOrder = $this->service->createPurchaseOrder([
        'supplier_id' => $this->supplier->id,
        'location_id' => $this->location->id,
        'order_date' => now()->toDateString(),
        'items' => [[
            'product_id' => $this->product->id,
            'quantity' => '3',
            'unit_cost' => '10.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.01',
        ]],
    ], $this->requester);
    $purchaseOrder = $this->service->approvePurchaseOrder($purchaseOrder, $this->approver);
    $purchaseOrderItem = $purchaseOrder->items->firstOrFail();

    $invoiceTaxes = ['0.00', '0.00', '0.01'];
    foreach ([0, 1, 2] as $index) {
        $this->service->receiveGoods($purchaseOrder->fresh(), [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'received_quantity' => '1',
                'accepted_quantity' => '1',
                'rejected_quantity' => '0',
            ]],
        ], $this->approver);

        $invoice = $this->service->createSupplierInvoice($purchaseOrder->fresh(), [
            'invoice_number' => 'GRNI-ROUND-'.$index.'-'.strtoupper(Str::random(5)),
            'invoice_date' => now()->toDateString(),
            'items' => [[
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'quantity' => '1',
                'unit_cost' => '10.00',
            ]],
            'tax_total' => $invoiceTaxes[$index],
            'discount_total' => '0.00',
        ], $this->requester);

        $this->service->postSupplierInvoice($invoice, $this->approver);
    }

    $grniAccountId = FinancialAccount::query()->where('code', '2050')->value('id');
    $receiptJournalIds = GoodsReceipt::query()
        ->where('purchase_order_id', $purchaseOrder->id)
        ->whereNotNull('journal_entry_id')
        ->pluck('journal_entry_id');
    $invoiceJournalIds = SupplierInvoice::query()
        ->where('purchase_order_id', $purchaseOrder->id)
        ->whereNotNull('journal_entry_id')
        ->pluck('journal_entry_id');

    $receivedGrni = (string) DB::table('journal_entry_lines')
        ->whereIn('journal_entry_id', $receiptJournalIds)
        ->where('account_id', $grniAccountId)
        ->sum('credit');
    $clearedGrni = (string) DB::table('journal_entry_lines')
        ->whereIn('journal_entry_id', $invoiceJournalIds)
        ->where('account_id', $grniAccountId)
        ->sum('debit');

    expect(bcadd($receivedGrni, '0', 2))->toBe('30.01')
        ->and(bcadd($clearedGrni, '0', 2))->toBe('30.01')
        ->and(bcsub($receivedGrni, $clearedGrni, 2))->toBe('0.00');

    $viewer = createProcurementApiUser(['system.procurement.view']);
    $viewer->locations()->attach($this->location->id, ['access_level' => 'view']);
    $report = $this->actingAs($viewer)
        ->getJson('/api/v1/backend/procurement/overview')
        ->assertOk()
        ->json('grni_reconciliation');

    $row = collect($report)->firstWhere('purchase_order_id', $purchaseOrder->id);
    expect($row)->not->toBeNull()
        ->and($row['received_value'])->toBe('30.01')
        ->and($row['invoiced_value'])->toBe('30.01')
        ->and($row['outstanding_value'])->toBe('0.00')
        ->and($row['status'])->toBe('matched');
});

it('allocates supplier return rounding remainders across partial returns', function (): void {
    $purchaseOrder = $this->service->createPurchaseOrder([
        'supplier_id' => $this->supplier->id,
        'location_id' => $this->location->id,
        'order_date' => now()->toDateString(),
        'items' => [[
            'product_id' => $this->product->id,
            'quantity' => '3',
            'unit_cost' => '10.00',
            'tax_amount' => '0.01',
        ]],
    ], $this->requester);
    $purchaseOrder = $this->service->approvePurchaseOrder($purchaseOrder, $this->approver);
    $purchaseOrderItem = $purchaseOrder->items->firstOrFail();

    $this->service->receiveGoods($purchaseOrder, [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'received_quantity' => '3',
            'accepted_quantity' => '3',
            'rejected_quantity' => '0',
        ]],
    ], $this->approver);

    $invoice = $this->service->createSupplierInvoice($purchaseOrder, [
        'invoice_number' => 'INV-'.strtoupper(Str::random(8)),
        'invoice_date' => now()->toDateString(),
        'items' => [[
            'purchase_order_item_id' => $purchaseOrderItem->id,
            'quantity' => '3',
            'unit_cost' => '10.00',
        ]],
        'tax_total' => '0.01',
        'discount_total' => '0.00',
    ], $this->requester);
    $invoice = $this->service->postSupplierInvoice($invoice, $this->approver);

    $returns = [];
    foreach ([1, 2, 3] as $index) {
        $returns[] = $this->service->returnToSupplier($invoice->fresh(), [
            'return_date' => now()->toDateString(),
            'reason' => 'Partial quality return '.$index,
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'purchase_order_item_id' => $purchaseOrderItem->id,
                'quantity' => '1',
            ]],
        ], $this->approver);
    }

    expect(array_map(fn (SupplierReturn $return): string => $return->total_amount, $returns))
        ->toBe(['10.00', '10.00', '10.01'])
        ->and((string) SupplierReturn::query()->where('supplier_invoice_id', $invoice->id)->sum('total_amount'))
        ->toBe('30.01')
        ->and($invoice->fresh()->status)->toBe('paid');
});
