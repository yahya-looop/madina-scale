<?php

use App\Models\FinancialAccount;
use App\Models\InventoryStock;
use App\Models\JournalEntryLine;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
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
