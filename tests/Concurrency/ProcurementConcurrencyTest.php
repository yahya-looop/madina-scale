<?php

use App\Models\GoodsReceipt;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\User;
use App\Services\ProcurementService;
use Illuminate\Support\Str;

/** @param positive-int $locationId
 * @param  positive-int  $productId
 */
function procurementConcurrencyOrder(
    User $requester,
    User $approver,
    Supplier $supplier,
    int $locationId,
    int $productId,
    string $quantity,
): PurchaseOrder {
    $service = app(ProcurementService::class);
    $purchaseOrder = $service->createPurchaseOrder([
        'supplier_id' => $supplier->id,
        'location_id' => $locationId,
        'order_date' => now()->toDateString(),
        'items' => [[
            'product_id' => $productId,
            'quantity' => $quantity,
            'unit_cost' => '100.00',
        ]],
    ], $requester);

    return $service->approvePurchaseOrder($purchaseOrder, $approver);
}

function procurementConcurrencySupplier(User $creator): Supplier
{
    return Supplier::query()->create([
        'supplier_code' => 'SUP-CON-'.Str::upper(Str::random(8)),
        'name' => 'Concurrency Supplier '.Str::upper(Str::random(5)),
        'status' => 'active',
        'created_by' => $creator->id,
    ]);
}

/** @return array<string, mixed> */
function procurementInvoicePayload(int $purchaseOrderItemId, string $invoiceNumber, string $quantity): array
{
    return [
        'invoice_number' => $invoiceNumber,
        'invoice_date' => now()->toDateString(),
        'items' => [[
            'purchase_order_item_id' => $purchaseOrderItemId,
            'quantity' => $quantity,
            'unit_cost' => '100.00',
        ]],
        'tax_total' => '0',
        'discount_total' => '0',
    ];
}

test('simultaneous goods receipts cannot over-receive one purchase order', function () {
    $requester = concurrencyUser(['system.procurement.view']);
    $approver = concurrencyUser(['system.procurement.view']);
    $location = inventoryTestLocation('proc-grn-'.Str::lower(Str::random(5)));
    $product = inventoryTestProduct();
    $supplier = procurementConcurrencySupplier($requester);
    $purchaseOrder = procurementConcurrencyOrder(
        $requester, $approver, $supplier, $location->id, $product->id, '10'
    );
    $itemId = (int) $purchaseOrder->items->firstOrFail()->id;

    $makeReceipt = static fn (): array => [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $itemId,
            'received_quantity' => '6',
            'accepted_quantity' => '0',
            'rejected_quantity' => '6',
            'rejection_reason' => 'Concurrent rejected receipt test',
        ]],
    ];

    [$one, $two] = startConcurrentWorkers(
        [
            'operation' => 'procurement_receive',
            'actor_id' => $approver->id,
            'purchase_order_id' => $purchaseOrder->id,
            'data' => $makeReceipt(),
        ],
        [
            'operation' => 'procurement_receive',
            'actor_id' => $approver->id,
            'purchase_order_id' => $purchaseOrder->id,
            'data' => $makeReceipt(),
        ],
    );
    $results = [assertConcurrencyWorkerCompleted($one), assertConcurrencyWorkerCompleted($two)];

    expect(collect($results)->where('ok', true)->count())->toBe(1);
    expect(collect($results)->where('ok', false)->count())->toBe(1);
    expect(GoodsReceipt::query()->where('purchase_order_id', $purchaseOrder->id)->count())->toBe(1);
    expect((string) $purchaseOrder->items()->firstOrFail()->fresh()->received_quantity)->toBe('6.0000');
});

test('simultaneous supplier invoices cannot bill beyond accepted quantity on one purchase order', function () {
    $requester = concurrencyUser(['system.procurement.view']);
    $approver = concurrencyUser(['system.procurement.view']);
    $location = inventoryTestLocation('proc-inv-'.Str::lower(Str::random(5)));
    $product = inventoryTestProduct();
    $supplier = procurementConcurrencySupplier($requester);
    $purchaseOrder = procurementConcurrencyOrder(
        $requester, $approver, $supplier, $location->id, $product->id, '10'
    );
    $item = $purchaseOrder->items->firstOrFail();

    app(ProcurementService::class)->receiveGoods($purchaseOrder, [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $item->id,
            'received_quantity' => '10',
            'accepted_quantity' => '10',
            'rejected_quantity' => '0',
        ]],
    ], $approver);

    [$one, $two] = startConcurrentWorkers(
        [
            'operation' => 'procurement_invoice',
            'actor_id' => $requester->id,
            'purchase_order_id' => $purchaseOrder->id,
            'data' => procurementInvoicePayload($item->id, 'CONC-INV-A-'.Str::upper(Str::random(5)), '6'),
        ],
        [
            'operation' => 'procurement_invoice',
            'actor_id' => $requester->id,
            'purchase_order_id' => $purchaseOrder->id,
            'data' => procurementInvoicePayload($item->id, 'CONC-INV-B-'.Str::upper(Str::random(5)), '6'),
        ],
    );
    $results = [assertConcurrencyWorkerCompleted($one), assertConcurrencyWorkerCompleted($two)];

    expect(collect($results)->where('ok', true)->count())->toBe(1);
    expect(collect($results)->where('ok', false)->count())->toBe(1);
    expect(SupplierInvoice::query()->where('purchase_order_id', $purchaseOrder->id)->count())->toBe(1);
    expect((string) SupplierInvoice::query()->where('purchase_order_id', $purchaseOrder->id)
        ->join('supplier_invoice_items', 'supplier_invoice_items.supplier_invoice_id', '=', 'supplier_invoices.id')
        ->sum('supplier_invoice_items.quantity'))->toBe('6.0000');
});

test('simultaneous invoice numbers are unique across different purchase orders for the same supplier', function () {
    $requester = concurrencyUser(['system.procurement.view']);
    $approver = concurrencyUser(['system.procurement.view']);
    $location = inventoryTestLocation('proc-dup-'.Str::lower(Str::random(5)));
    $product = inventoryTestProduct();
    $supplier = procurementConcurrencySupplier($requester);
    $firstOrder = procurementConcurrencyOrder(
        $requester, $approver, $supplier, $location->id, $product->id, '1'
    );
    $secondOrder = procurementConcurrencyOrder(
        $requester, $approver, $supplier, $location->id, $product->id, '1'
    );

    foreach ([$firstOrder, $secondOrder] as $order) {
        $item = $order->items->firstOrFail();
        app(ProcurementService::class)->receiveGoods($order, [
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'purchase_order_item_id' => $item->id,
                'received_quantity' => '1',
                'accepted_quantity' => '1',
                'rejected_quantity' => '0',
            ]],
        ], $approver);
    }

    $sharedInvoiceNumber = 'SHARED-'.Str::upper(Str::random(8));
    [$one, $two] = startConcurrentWorkers(
        [
            'operation' => 'procurement_invoice',
            'actor_id' => $requester->id,
            'purchase_order_id' => $firstOrder->id,
            'data' => procurementInvoicePayload($firstOrder->items->firstOrFail()->id, $sharedInvoiceNumber, '1'),
        ],
        [
            'operation' => 'procurement_invoice',
            'actor_id' => $requester->id,
            'purchase_order_id' => $secondOrder->id,
            'data' => procurementInvoicePayload($secondOrder->items->firstOrFail()->id, $sharedInvoiceNumber, '1'),
        ],
    );
    $results = [assertConcurrencyWorkerCompleted($one), assertConcurrencyWorkerCompleted($two)];

    expect(collect($results)->where('ok', true)->count())->toBe(1);
    expect(collect($results)->where('ok', false)->count())->toBe(1);
    expect(SupplierInvoice::query()->where('supplier_id', $supplier->id)
        ->where('invoice_number', $sharedInvoiceNumber)->count())->toBe(1);
});

test('simultaneous supplier invoice posting creates exactly one journal entry', function () {
    $requester = concurrencyUser(['system.procurement.view']);
    $approver = concurrencyUser(['system.procurement.view']);
    $location = inventoryTestLocation('proc-post-'.Str::lower(Str::random(5)));
    $product = inventoryTestProduct();
    $supplier = procurementConcurrencySupplier($requester);
    $purchaseOrder = procurementConcurrencyOrder(
        $requester, $approver, $supplier, $location->id, $product->id, '2'
    );
    $item = $purchaseOrder->items->firstOrFail();

    app(ProcurementService::class)->receiveGoods($purchaseOrder, [
        'idempotency_key' => (string) Str::uuid(),
        'items' => [[
            'purchase_order_item_id' => $item->id,
            'received_quantity' => '2',
            'accepted_quantity' => '2',
            'rejected_quantity' => '0',
        ]],
    ], $approver);

    $invoice = app(ProcurementService::class)->createSupplierInvoice(
        $purchaseOrder->fresh(),
        procurementInvoicePayload($item->id, 'POST-RACE-'.Str::upper(Str::random(8)), '2'),
        $requester,
    );

    [$one, $two] = startConcurrentWorkers(
        ['operation' => 'procurement_post_invoice', 'actor_id' => $approver->id, 'invoice_id' => $invoice->id],
        ['operation' => 'procurement_post_invoice', 'actor_id' => $approver->id, 'invoice_id' => $invoice->id],
    );
    $results = [assertConcurrencyWorkerCompleted($one), assertConcurrencyWorkerCompleted($two)];

    expect(collect($results)->where('ok', true)->count())->toBe(1);
    expect(collect($results)->where('ok', false)->count())->toBe(1);
    expect($invoice->fresh()->status)->toBe('posted');
    expect($invoice->fresh()->journal_entry_id)->not->toBeNull();
    expect(DB::table('journal_entries')->where('source', 'procurement_invoice')
        ->where('reference', $invoice->invoice_number)->count())->toBe(1);
});
