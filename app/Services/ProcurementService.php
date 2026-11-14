<?php

namespace App\Services;

use App\Models\InventoryValuationMovement;
use App\Models\FinancialAccount;
use App\Models\GoodsReceipt;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\PurchaseOrderRevision;
use App\Models\PurchaseRequisition;
use App\Models\Supplier;
use App\Models\SupplierInvoice;
use App\Models\SupplierPayment;
use App\Models\SupplierReturn;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProcurementService
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly FinancialLedgerService $ledger,
        private readonly ActivityLogService $activityLog,
    ) {}

    /** @param array<string,mixed> $data */
    public function createRequisition(array $data, User $actor): PurchaseRequisition
    {
        return DB::transaction(function () use ($data, $actor): PurchaseRequisition {
            $location = Location::query()->lockForUpdate()->findOrFail((int) $data['location_id']);
            abort_unless($location->is_active, 422, 'The selected location is inactive.');
            $requisition = PurchaseRequisition::query()->create([
                'requisition_number' => $this->number('PR'),
                'location_id' => $location->id,
                'status' => 'draft',
                'purpose' => $data['purpose'] ?? null,
                'required_by' => $data['required_by'] ?? null,
                'requested_by' => $actor->id,
            ]);
            foreach ($data['items'] as $item) {
                $owner = $this->validateOwner($item);
                $quantity = $this->quantity($item['quantity']);
                if (bccomp($quantity, '0', 4) <= 0) {
                    throw ValidationException::withMessages(['items' => 'Requisition quantity must be greater than zero.']);
                }
                $requisition->items()->create([
                    'product_id' => $owner['product_id'],
                    'product_variant_id' => $owner['variant_id'],
                    'quantity' => $quantity,
                    'notes' => $item['notes'] ?? null,
                ]);
            }
            $this->activityLog->recordForUser($actor, 'created', 'procurement', 'Created purchase requisition '.$requisition->requisition_number, $requisition);

            return $requisition->load('items');
        });
    }

    public function submitRequisition(PurchaseRequisition $requisition, User $actor): PurchaseRequisition
    {
        return DB::transaction(function () use ($requisition, $actor): PurchaseRequisition {
            $locked = PurchaseRequisition::query()->lockForUpdate()->with('items')->findOrFail($requisition->id);
            if (! in_array($locked->status, ['draft', 'rejected'], true) || (int) $locked->requested_by !== (int) $actor->id) {
                throw ValidationException::withMessages(['status' => 'Only the requester can submit or resubmit their draft/rejected requisition.']);
            }
            if ($locked->items->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'A requisition must contain at least one item.']);
            }
            $locked->update(['status' => 'submitted', 'submitted_by' => $actor->id, 'submitted_at' => now(), 'rejected_by' => null, 'rejected_at' => null, 'rejection_reason' => null]);
            $this->activityLog->recordForUser($actor, 'submitted', 'procurement', 'Submitted purchase requisition '.$locked->requisition_number, $locked);

            return $locked->load('items');
        }, 3);
    }

    public function rejectRequisition(PurchaseRequisition $requisition, string $reason, User $actor): PurchaseRequisition
    {
        return DB::transaction(function () use ($requisition, $reason, $actor): PurchaseRequisition {
            $locked = PurchaseRequisition::query()->lockForUpdate()->findOrFail($requisition->id);
            if (! in_array($locked->status, ['submitted', 'draft'], true) || (int) $locked->requested_by === (int) $actor->id) {
                throw ValidationException::withMessages(['status' => 'This requisition cannot be rejected by its requester or in its current state.']);
            }
            $locked->update(['status' => 'rejected', 'rejected_by' => $actor->id, 'rejected_at' => now(), 'rejection_reason' => trim($reason)]);
            $this->activityLog->recordForUser($actor, 'rejected', 'procurement', 'Rejected purchase requisition '.$locked->requisition_number, $locked);

            return $locked->load('items');
        }, 3);
    }

    public function cancelRequisition(PurchaseRequisition $requisition, User $actor): PurchaseRequisition
    {
        return DB::transaction(function () use ($requisition, $actor): PurchaseRequisition {
            $locked = PurchaseRequisition::query()->lockForUpdate()->findOrFail($requisition->id);
            if (! in_array($locked->status, ['draft', 'rejected'], true) || (int) $locked->requested_by !== (int) $actor->id) {
                throw ValidationException::withMessages(['status' => 'Only the requester can cancel a draft or rejected requisition.']);
            }
            $locked->update(['status' => 'cancelled']);
            $this->activityLog->recordForUser($actor, 'cancelled', 'procurement', 'Cancelled purchase requisition '.$locked->requisition_number, $locked);

            return $locked;
        }, 3);
    }

    public function approveRequisition(PurchaseRequisition $requisition, User $actor): PurchaseRequisition
    {
        return DB::transaction(function () use ($requisition, $actor): PurchaseRequisition {
            $locked = PurchaseRequisition::query()->lockForUpdate()->findOrFail($requisition->id);
            if ($locked->status !== 'draft' && $locked->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'Only draft or submitted requisitions can be approved.']);
            }
            if ((int) $locked->requested_by === (int) $actor->id) {
                throw ValidationException::withMessages(['status' => 'The requester cannot approve their own purchase requisition.']);
            }
            $locked->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->activityLog->recordForUser($actor, 'approved', 'procurement', 'Approved purchase requisition '.$locked->requisition_number, $locked);

            return $locked->load('items');
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function createPurchaseOrder(array $data, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($data, $actor): PurchaseOrder {
            $supplier = Supplier::query()->lockForUpdate()->findOrFail((int) $data['supplier_id']);
            if ($supplier->status !== 'active') {
                throw ValidationException::withMessages(['supplier_id' => 'Only active suppliers can be used.']);
            }
            $location = Location::query()->lockForUpdate()->findOrFail((int) $data['location_id']);
            if (! $location->is_active) {
                throw ValidationException::withMessages(['location_id' => 'The selected location is inactive.']);
            }
            $requisition = null;
            if (! empty($data['purchase_requisition_id'])) {
                $requisition = PurchaseRequisition::query()->lockForUpdate()->with('items')->findOrFail((int) $data['purchase_requisition_id']);
                if ($requisition->status !== 'approved' || $requisition->location_id !== $location->id || $requisition->converted_purchase_order_id !== null) {
                    throw ValidationException::withMessages(['purchase_requisition_id' => 'The requisition must be approved, match the location and not already be converted.']);
                }
            }

            $po = PurchaseOrder::query()->create([
                'po_number' => $this->number('PO'),
                'supplier_id' => $supplier->id,
                'location_id' => $location->id,
                'purchase_requisition_id' => $requisition?->id,
                'status' => 'draft',
                'order_date' => $data['order_date'] ?? now()->toDateString(),
                'expected_at' => $data['expected_at'] ?? null,
                'currency' => strtoupper($data['currency'] ?? 'BDT'),
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
            ]);

            $subtotal = '0.00';
            $discountTotal = '0.00';
            $taxTotal = '0.00';
            $requisitionQuantities = [];
            foreach ($data['items'] as $item) {
                $owner = $this->validateOwner($item);
                $qty = $this->quantity($item['quantity']);
                if (bccomp($qty, '0', 4) <= 0) {
                    throw ValidationException::withMessages(['items' => 'Purchase order quantity must be greater than zero.']);
                }
                if ($requisition !== null) {
                    $requestedLine = $requisition->items->first(function ($requestLine) use ($owner): bool {
                        return $requestLine->product_id === $owner['product_id']
                            && $requestLine->product_variant_id === $owner['variant_id'];
                    });
                    if (! $requestedLine) {
                        throw ValidationException::withMessages(['items' => 'Purchase order lines must match the approved requisition.']);
                    }
                    $requestedKey = (string) $requestedLine->id;
                    $requisitionQuantities[$requestedKey] = bcadd($requisitionQuantities[$requestedKey] ?? '0.0000', $qty, 4);
                    if (bccomp($requisitionQuantities[$requestedKey], (string) $requestedLine->quantity, 4) > 0) {
                        throw ValidationException::withMessages(['items' => 'Combined purchase order quantity cannot exceed the approved requisition quantity.']);
                    }
                }
                $unitCost = $this->money($item['unit_cost']);
                if (bccomp($unitCost, '0.00', 2) <= 0) {
                    throw ValidationException::withMessages(['items' => 'Purchase order unit cost must be greater than zero.']);
                }
                $discount = $this->money($item['discount_amount'] ?? '0');
                $tax = $this->money($item['tax_amount'] ?? '0');
                $gross = bcmul($qty, $unitCost, 2);
                if (bccomp($discount, $gross, 2) > 0) {
                    throw ValidationException::withMessages(['items' => 'Line discount cannot exceed its gross value.']);
                }
                $lineTotal = bcadd(bcsub($gross, $discount, 2), $tax, 2);
                $po->items()->create([
                    'product_id' => $owner['product_id'],
                    'product_variant_id' => $owner['variant_id'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $qty,
                    'unit_cost' => $unitCost,
                    'discount_amount' => $discount,
                    'tax_amount' => $tax,
                    'line_total' => $lineTotal,
                ]);
                $subtotal = bcadd($subtotal, $gross, 2);
                $discountTotal = bcadd($discountTotal, $discount, 2);
                $taxTotal = bcadd($taxTotal, $tax, 2);
            }
            if ($requisition !== null) {
                foreach ($requisition->items as $requestedLine) {
                    if (bccomp($requisitionQuantities[(string) $requestedLine->id] ?? '0.0000', (string) $requestedLine->quantity, 4) !== 0) {
                        throw ValidationException::withMessages(['items' => 'The purchase order must fully cover every approved requisition line.']);
                    }
                }
            }
            $po->update([
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'grand_total' => bcadd(bcsub($subtotal, $discountTotal, 2), $taxTotal, 2),
            ]);
            if ($requisition) {
                $requisition->update(['status' => 'converted', 'converted_purchase_order_id' => $po->id]);
            }
            $this->activityLog->recordForUser($actor, 'created', 'procurement', 'Created purchase order '.$po->po_number, $po);

            return $po->load(['supplier', 'items', 'location']);
        }, 3);
    }

    public function submitPurchaseOrder(PurchaseOrder $purchaseOrder, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor): PurchaseOrder {
            $po = PurchaseOrder::query()->lockForUpdate()->with('items')->findOrFail($purchaseOrder->id);
            if (! in_array($po->status, ['draft', 'rejected'], true) || (int) $po->created_by !== (int) $actor->id) {
                throw ValidationException::withMessages(['status' => 'Only the purchase order creator can submit or resubmit a draft/rejected order.']);
            }
            if ($po->items->isEmpty() || bccomp((string) $po->grand_total, '0', 2) <= 0) {
                throw ValidationException::withMessages(['items' => 'A purchase order must contain items and a positive total.']);
            }
            $po->update(['status' => 'submitted', 'submitted_by' => $actor->id, 'submitted_at' => now(), 'rejected_by' => null, 'rejected_at' => null, 'rejection_reason' => null]);
            $this->activityLog->recordForUser($actor, 'submitted', 'procurement', 'Submitted purchase order '.$po->po_number, $po);

            return $po->load(['supplier', 'items']);
        }, 3);
    }

    public function rejectPurchaseOrder(PurchaseOrder $purchaseOrder, string $reason, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $reason, $actor): PurchaseOrder {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            if (! in_array($po->status, ['submitted', 'draft'], true) || (int) $po->created_by === (int) $actor->id) {
                throw ValidationException::withMessages(['status' => 'This purchase order cannot be rejected by its creator or in its current state.']);
            }
            $po->update(['status' => 'rejected', 'rejected_by' => $actor->id, 'rejected_at' => now(), 'rejection_reason' => trim($reason)]);
            $this->activityLog->recordForUser($actor, 'rejected', 'procurement', 'Rejected purchase order '.$po->po_number, $po);

            return $po->load(['supplier', 'items']);
        }, 3);
    }

    public function cancelPurchaseOrder(PurchaseOrder $purchaseOrder, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor): PurchaseOrder {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            if (! in_array($po->status, ['draft', 'rejected'], true) || (int) $po->created_by !== (int) $actor->id) {
                throw ValidationException::withMessages(['status' => 'Only the creator can cancel a draft or rejected purchase order.']);
            }
            $po->update(['status' => 'cancelled']);
            $this->activityLog->recordForUser($actor, 'cancelled', 'procurement', 'Cancelled purchase order '.$po->po_number, $po);

            return $po;
        }, 3);
    }

    public function approvePurchaseOrder(PurchaseOrder $purchaseOrder, User $actor): PurchaseOrder
    {
        return DB::transaction(function () use ($purchaseOrder, $actor): PurchaseOrder {
            $po = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->id);
            if ($po->status !== 'draft' && $po->status !== 'submitted') {
                throw ValidationException::withMessages(['status' => 'Only draft or submitted purchase orders can be approved.']);
            }
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($po->supplier_id);
            if ($supplier->status !== 'active') {
                throw ValidationException::withMessages(['supplier_id' => 'This purchase order cannot be approved because the supplier is '.$supplier->status.'.']);
            }
            if ((int) $po->created_by === (int) $actor->id) {
                throw ValidationException::withMessages(['status' => 'The creator cannot approve their own purchase order.']);
            }
            if ($po->items()->count() === 0 || bccomp((string) $po->grand_total, '0', 2) <= 0) {
                throw ValidationException::withMessages(['items' => 'A purchase order must contain items and a positive total.']);
            }
            $po->update(['status' => 'approved', 'approved_by' => $actor->id, 'approved_at' => now()]);
            $this->activityLog->recordForUser($actor, 'approved', 'procurement', 'Approved purchase order '.$po->po_number, $po);

            return $po->load(['supplier', 'items']);
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function receiveGoods(PurchaseOrder $purchaseOrder, array $data, User $actor): GoodsReceipt
    {
        return DB::transaction(function () use ($purchaseOrder, $data, $actor): GoodsReceipt {
            $po = PurchaseOrder::query()->lockForUpdate()->with('items')->findOrFail($purchaseOrder->id);
            $key = trim((string) ($data['idempotency_key'] ?? ''));
            if ($key === '') {
                throw ValidationException::withMessages(['idempotency_key' => 'A receipt idempotency key is required.']);
            }
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::selectOne('SELECT pg_advisory_xact_lock(hashtext(?))', ['procurement-grn:'.$key]);
            }
            $hash = hash('sha256', json_encode(collect($data)->except('idempotency_key')->sortKeys()->all(), JSON_THROW_ON_ERROR));
            $existingReceipt = GoodsReceipt::query()->where('idempotency_key', $key)->first();
            if ($existingReceipt) {
                if (! hash_equals($existingReceipt->payload_hash, $hash) || $existingReceipt->purchase_order_id !== $po->id) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This receipt key was already used with different data.']);
                }

                return $existingReceipt->load('items');
            }
            if (! in_array($po->status, ['approved', 'partially_received'], true)) {
                throw ValidationException::withMessages(['purchase_order' => 'Only approved purchase orders can receive goods.']);
            }
            $receipt = GoodsReceipt::query()->create([
                'grn_number' => $this->number('GRN'),
                'purchase_order_id' => $po->id,
                'supplier_id' => $po->supplier_id,
                'location_id' => $po->location_id,
                'received_date' => $data['received_date'] ?? now()->toDateString(),
                'status' => 'posted',
                'idempotency_key' => $key,
                'payload_hash' => $hash,
                'delivery_note' => $data['delivery_note'] ?? null,
                'notes' => $data['notes'] ?? null,
                'received_by' => $actor->id,
            ]);
            $receiptValue = '0.00';
            $seenReceiptItems = [];
            foreach ($data['items'] as $line) {
                $poItem = $po->items->firstWhere('id', (int) $line['purchase_order_item_id']);
                if (! $poItem) {
                    throw ValidationException::withMessages(['items' => 'A receipt line does not belong to this purchase order.']);
                }
                if (isset($seenReceiptItems[$poItem->id])) {
                    throw ValidationException::withMessages(['items' => 'Each purchase order line can appear only once per goods receipt.']);
                }
                $seenReceiptItems[$poItem->id] = true;
                $received = $this->quantity($line['received_quantity']);
                $accepted = $this->quantity($line['accepted_quantity']);
                $rejected = $this->quantity($line['rejected_quantity'] ?? '0');
                if (bccomp(bcadd($accepted, $rejected, 4), $received, 4) !== 0 || bccomp($accepted, '0', 4) < 0) {
                    throw ValidationException::withMessages(['items' => 'Received quantity must equal accepted plus rejected quantity.']);
                }
                $remainingOrdered = bcsub((string) $poItem->quantity, (string) $poItem->received_quantity, 4);
                $remainingAcceptable = bcsub((string) $poItem->quantity, (string) $poItem->accepted_quantity, 4);
                if (bccomp($received, '0', 4) <= 0 || bccomp($received, $remainingOrdered, 4) > 0 || bccomp($accepted, $remainingAcceptable, 4) > 0) {
                    throw ValidationException::withMessages(['items' => 'Receipt must be positive and total received quantity cannot exceed the outstanding ordered quantity.']);
                }
                if (bccomp($rejected, '0', 4) > 0 && trim((string) ($line['rejection_reason'] ?? '')) === '') {
                    throw ValidationException::withMessages(['items' => 'A rejection reason is required when any received quantity is rejected.']);
                }

                $movement = null;
                if (bccomp($accepted, '0', 4) > 0) {
                    // Cumulative allocation keeps partial receipt cents exact.
                    $previousAccepted = (string) $poItem->accepted_quantity;
                    $cumulativeAccepted = bcadd($previousAccepted, $accepted, 4);
                    $previousReceiptValue = bcmul((string) $poItem->line_total, bcdiv($previousAccepted, (string) $poItem->quantity, 8), 2);
                    $cumulativeReceiptValue = bcmul((string) $poItem->line_total, bcdiv($cumulativeAccepted, (string) $poItem->quantity, 8), 2);
                    $receiptLineValue = bcsub($cumulativeReceiptValue, $previousReceiptValue, 2);
                    $receiptValue = bcadd($receiptValue, $receiptLineValue, 2);
                    $unitCost = bcdiv($receiptLineValue, $accepted, 6);
                    $movement = $this->inventory->receive([
                        'location_id' => $po->location_id,
                        'product_id' => $poItem->product_id,
                        'product_variant_id' => $poItem->product_variant_id,
                        'quantity' => $accepted,
                        'unit_cost' => $unitCost,
                        'reason' => 'Purchase receipt '.$receipt->grn_number,
                        'reference_type' => 'goods_receipt',
                        'reference_id' => (string) $receipt->id,
                        'idempotency_key' => (string) Str::uuid(),
                        'metadata' => ['purchase_order_id' => $po->id, 'purchase_order_item_id' => $poItem->id, 'receipt_line_value' => $receiptLineValue],
                    ], $actor);
                }

                $receipt->items()->create([
                    'purchase_order_item_id' => $poItem->id,
                    'received_quantity' => $received,
                    'accepted_quantity' => $accepted,
                    'rejected_quantity' => $rejected,
                    'rejection_reason' => $line['rejection_reason'] ?? null,
                    'stock_movement_id' => $movement?->id,
                ]);
                $poItem->received_quantity = bcadd((string) $poItem->received_quantity, $received, 4);
                $poItem->accepted_quantity = bcadd((string) $poItem->accepted_quantity, $accepted, 4);
                $poItem->save();
            }
            if (bccomp($receiptValue, '0', 2) > 0) {
                $inventoryAccount = FinancialAccount::query()->where('code', '1200')->where('is_active', true)->firstOrFail();
                $grniAccount = FinancialAccount::query()->where('code', '2050')->where('is_active', true)->firstOrFail();
                $journal = $this->ledger->createDraft([
                    'source' => 'procurement_grn',
                    'entry_date' => $receipt->received_date->toDateString(),
                    'description' => 'Goods receipt '.$receipt->grn_number,
                    'reference' => $receipt->grn_number,
                    'location_id' => $po->location_id,
                    'lines' => [
                        ['account_id' => $inventoryAccount->id, 'debit' => $receiptValue, 'credit' => '0', 'supplier_id' => $po->supplier_id],
                        ['account_id' => $grniAccount->id, 'debit' => '0', 'credit' => $receiptValue, 'supplier_id' => $po->supplier_id],
                    ],
                ], $actor);
                $posted = $this->ledger->post($journal, $actor);
                $receipt->update(['journal_entry_id' => $posted->id]);
            }
            $po->refresh()->load('items');
            // PO fulfillment tracks physical delivery. Rejected units are recorded separately
            // and are excluded from invoicing, but they still complete this delivery.
            // Replacement goods require a separate PO because total physical receipts
            // are capped at the ordered quantity.
            $fullyReceived = $po->items->every(fn (PurchaseOrderItem $item): bool => bccomp((string) $item->received_quantity, (string) $item->quantity, 4) >= 0);
            $po->update(['status' => $fullyReceived ? 'received' : 'partially_received']);
            $this->activityLog->recordForUser($actor, 'received', 'procurement', 'Posted goods receipt '.$receipt->grn_number, $receipt);

            return $receipt->load(['items', 'journalEntry']);
        }, 3);
    }

    /** @param array<string,mixed> $data */

    /**
     * Submit a controlled change request for an approved PO.
     *
     * @param  array<string, mixed>  $data
     */
    public function requestPurchaseOrderRevision(PurchaseOrder $purchaseOrder, array $data, User $actor): PurchaseOrderRevision
    {
        return DB::transaction(function () use ($purchaseOrder, $data, $actor): PurchaseOrderRevision {
            $po = PurchaseOrder::query()->lockForUpdate()->with('items')->findOrFail($purchaseOrder->id);
            if ($po->status !== 'approved') {
                throw ValidationException::withMessages(['status' => 'Only approved purchase orders can enter the revision workflow.']);
            }
            if ($po->receipts()->exists() || $po->invoices()->exists()) {
                throw ValidationException::withMessages(['purchase_order' => 'A purchase order cannot be revised after goods receipt or supplier invoicing has started.']);
            }
            if ($po->revisions()->where('status', 'submitted')->exists()) {
                throw ValidationException::withMessages(['revision' => 'This purchase order already has a pending revision.']);
            }

            $lines = [];
            foreach ($data['items'] as $item) {
                $owner = $this->validateOwner($item);
                $quantity = $this->quantity($item['quantity']);
                $unitCost = $this->money($item['unit_cost']);
                $discount = $this->money($item['discount_amount'] ?? '0');
                $tax = $this->money($item['tax_amount'] ?? '0');
                if (bccomp($quantity, '0', 4) <= 0 || bccomp($unitCost, '0', 2) <= 0) {
                    throw ValidationException::withMessages(['items' => 'Revision quantities and unit costs must be greater than zero.']);
                }
                $gross = bcmul($quantity, $unitCost, 2);
                if (bccomp($discount, $gross, 2) > 0) {
                    throw ValidationException::withMessages(['items' => 'A revision line discount cannot exceed its gross value.']);
                }
                $lines[] = [
                    'product_id' => $owner['product_id'],
                    'product_variant_id' => $owner['variant_id'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'discount_amount' => $discount,
                    'tax_amount' => $tax,
                    'line_total' => bcadd(bcsub($gross, $discount, 2), $tax, 2),
                ];
            }
            if ($lines === []) {
                throw ValidationException::withMessages(['items' => 'A revision must contain at least one line.']);
            }

            $number = ((int) $po->revisions()->max('revision_number')) + 1;
            $revision = $po->revisions()->create([
                'revision_number' => $number,
                'status' => 'submitted',
                'reason' => trim((string) $data['reason']),
                'proposed_data' => [
                    'order_date' => $data['order_date'] ?? $po->order_date->toDateString(),
                    'expected_at' => $data['expected_at'] ?? null,
                    'currency' => strtoupper((string) ($data['currency'] ?? $po->currency)),
                    'notes' => $data['notes'] ?? $po->notes,
                    'items' => $lines,
                ],
                'created_by' => $actor->id,
                'submitted_at' => now(),
            ]);
            $this->activityLog->recordForUser($actor, 'revision_submitted', 'procurement', 'Submitted revision '.$number.' for purchase order '.$po->po_number, $revision);

            return $revision;
        }, 3);
    }

    public function approvePurchaseOrderRevision(PurchaseOrderRevision $revision, User $actor): PurchaseOrderRevision
    {
        return DB::transaction(function () use ($revision, $actor): PurchaseOrderRevision {
            $lockedRevision = PurchaseOrderRevision::query()->lockForUpdate()->findOrFail($revision->id);
            $po = PurchaseOrder::query()->lockForUpdate()->with('items')->findOrFail($lockedRevision->purchase_order_id);
            if ($lockedRevision->status !== 'submitted' || $po->status !== 'approved') {
                throw ValidationException::withMessages(['revision' => 'Only pending revisions for approved purchase orders can be approved.']);
            }
            if ((int) $lockedRevision->created_by === (int) $actor->id) {
                throw ValidationException::withMessages(['revision' => 'The revision requester cannot approve their own revision.']);
            }
            if ($po->receipts()->exists() || $po->invoices()->exists()) {
                throw ValidationException::withMessages(['purchase_order' => 'This purchase order is no longer eligible for revision because receiving or invoicing has started.']);
            }

            $payload = $lockedRevision->proposed_data;
            $items = $payload['items'] ?? [];
            if ($items === []) {
                throw ValidationException::withMessages(['items' => 'The approved revision must contain at least one item.']);
            }
            $po->items()->delete();
            foreach ($items as $item) {
                $po->items()->create($item);
            }
            $subtotal = '0.00';
            $discountTotal = '0.00';
            $taxTotal = '0.00';
            foreach ($items as $item) {
                /** @var numeric-string $quantity */
                $quantity = (string) $item['quantity'];
                /** @var numeric-string $unitCost */
                $unitCost = (string) $item['unit_cost'];
                /** @var numeric-string $discount */
                $discount = (string) $item['discount_amount'];
                /** @var numeric-string $tax */
                $tax = (string) $item['tax_amount'];

                $subtotal = bcadd($subtotal, bcmul($quantity, $unitCost, 2), 2);
                $discountTotal = bcadd($discountTotal, $discount, 2);
                $taxTotal = bcadd($taxTotal, $tax, 2);
            }
            $po->update([
                'order_date' => $payload['order_date'],
                'expected_at' => $payload['expected_at'],
                'currency' => $payload['currency'],
                'notes' => $payload['notes'],
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'grand_total' => bcadd(bcsub($subtotal, $discountTotal, 2), $taxTotal, 2),
                'revision_no' => $lockedRevision->revision_number + 1,
            ]);
            $lockedRevision->update(['status' => 'approved', 'reviewed_by' => $actor->id, 'reviewed_at' => now()]);
            $this->activityLog->recordForUser($actor, 'revision_approved', 'procurement', 'Approved revision '.$lockedRevision->revision_number.' for purchase order '.$po->po_number, $lockedRevision);

            return $lockedRevision->fresh();
        }, 3);
    }

    public function rejectPurchaseOrderRevision(PurchaseOrderRevision $revision, string $reason, User $actor): PurchaseOrderRevision
    {
        return DB::transaction(function () use ($revision, $reason, $actor): PurchaseOrderRevision {
            $locked = PurchaseOrderRevision::query()->lockForUpdate()->findOrFail($revision->id);
            if ($locked->status !== 'submitted' || (int) $locked->created_by === (int) $actor->id) {
                throw ValidationException::withMessages(['revision' => 'This revision cannot be rejected by its requester or in its current state.']);
            }
            $locked->update(['status' => 'rejected', 'reviewed_by' => $actor->id, 'reviewed_at' => now(), 'rejection_reason' => trim($reason)]);
            $this->activityLog->recordForUser($actor, 'revision_rejected', 'procurement', 'Rejected revision '.$locked->revision_number, $locked);

            return $locked;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function createSupplierInvoice(PurchaseOrder $purchaseOrder, array $data, User $actor): SupplierInvoice
    {
        return DB::transaction(function () use ($purchaseOrder, $data, $actor): SupplierInvoice {
            $po = PurchaseOrder::query()->lockForUpdate()->with('items')->findOrFail($purchaseOrder->id);
            if (! in_array($po->status, ['partially_received', 'received'], true)) {
                throw ValidationException::withMessages(['purchase_order' => 'Supplier invoices can only be recorded after goods have been received.']);
            }

            $subtotal = '0.00';
            $expectedTotal = '0.00';
            $expectedDiscountTotal = '0.00';
            $expectedTaxTotal = '0.00';
            $validatedLines = [];
            $seenPurchaseOrderItems = [];
            foreach ($data['items'] as $line) {
                $poItem = $po->items->firstWhere('id', (int) $line['purchase_order_item_id']);
                if (! $poItem) {
                    throw ValidationException::withMessages(['items' => 'An invoice line does not belong to this purchase order.']);
                }
                if (isset($seenPurchaseOrderItems[$poItem->id])) {
                    throw ValidationException::withMessages(['items' => 'Each purchase order line can appear only once per supplier invoice.']);
                }
                $seenPurchaseOrderItems[$poItem->id] = true;
                $quantity = $this->quantity($line['quantity']);
                $unitCost = $this->money($line['unit_cost']);
                if (bccomp($unitCost, (string) $poItem->unit_cost, 2) !== 0) {
                    throw ValidationException::withMessages(['items' => 'Supplier invoice unit cost must match the approved purchase order unit cost.']);
                }
                $alreadyBilled = (string) (DB::table('supplier_invoice_items')
                    ->join('supplier_invoices', 'supplier_invoices.id', '=', 'supplier_invoice_items.supplier_invoice_id')
                    ->where('supplier_invoice_items.purchase_order_item_id', $poItem->id)
                    ->whereIn('supplier_invoices.status', ['draft', 'posted', 'partially_paid', 'paid'])
                    ->sum('supplier_invoice_items.quantity'));
                $alreadyAllocated = (string) (DB::table('supplier_invoice_items')
                    ->join('supplier_invoices', 'supplier_invoices.id', '=', 'supplier_invoice_items.supplier_invoice_id')
                    ->where('supplier_invoice_items.purchase_order_item_id', $poItem->id)
                    ->whereIn('supplier_invoices.status', ['draft', 'posted', 'partially_paid', 'paid'])
                    ->sum('supplier_invoice_items.line_total'));
                $remaining = bcsub((string) $poItem->accepted_quantity, $alreadyBilled, 4);
                if (bccomp($quantity, '0', 4) <= 0 || bccomp($quantity, $remaining, 4) > 0) {
                    throw ValidationException::withMessages(['items' => 'Invoice quantity exceeds accepted goods not already invoiced.']);
                }
                $lineSubtotal = bcmul($quantity, $unitCost, 2);
                // Match invoice value cumulatively per PO line so partial
                // invoices consume the same 2-decimal cumulative allocation as GRNs.
                $cumulativeBilled = bcadd($alreadyBilled, $quantity, 4);
                $cumulativeAllocated = bcmul(
                    (string) $poItem->line_total,
                    bcdiv($cumulativeBilled, (string) $poItem->quantity, 8),
                    2,
                );
                $matchedLineTotal = bcsub($cumulativeAllocated, $alreadyAllocated, 2);

                // Reconcile PO discount and tax components independently.
                // Derive each partial invoice allocation from cumulative billed
                // quantity so rounding remainders land on the final invoice.
                $cumulativeDiscount = bcmul(
                    (string) $poItem->discount_amount,
                    bcdiv($cumulativeBilled, (string) $poItem->quantity, 8),
                    2,
                );
                $previousDiscount = bcmul(
                    (string) $poItem->discount_amount,
                    bcdiv($alreadyBilled, (string) $poItem->quantity, 8),
                    2,
                );
                $expectedDiscountTotal = bcadd(
                    $expectedDiscountTotal,
                    bcsub($cumulativeDiscount, $previousDiscount, 2),
                    2,
                );

                $cumulativeTax = bcmul(
                    (string) $poItem->tax_amount,
                    bcdiv($cumulativeBilled, (string) $poItem->quantity, 8),
                    2,
                );
                $previousTax = bcmul(
                    (string) $poItem->tax_amount,
                    bcdiv($alreadyBilled, (string) $poItem->quantity, 8),
                    2,
                );
                $expectedTaxTotal = bcadd(
                    $expectedTaxTotal,
                    bcsub($cumulativeTax, $previousTax, 2),
                    2,
                );

                $subtotal = bcadd($subtotal, $lineSubtotal, 2);
                $expectedTotal = bcadd($expectedTotal, $matchedLineTotal, 2);
                $validatedLines[] = [
                    'purchase_order_item_id' => $poItem->id,
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'line_total' => $matchedLineTotal,
                ];
            }

            $tax = $this->money($data['tax_total'] ?? '0');
            $discount = $this->money($data['discount_total'] ?? '0');
            if (bccomp($discount, $subtotal, 2) > 0) {
                throw ValidationException::withMessages(['discount_total' => 'Invoice discount cannot exceed its subtotal.']);
            }
            if (bccomp($discount, $expectedDiscountTotal, 2) !== 0) {
                throw ValidationException::withMessages(['discount_total' => 'Supplier invoice discount must match the approved purchase order discount allocation for the invoiced quantities.']);
            }
            if (bccomp($tax, $expectedTaxTotal, 2) !== 0) {
                throw ValidationException::withMessages(['tax_total' => 'Supplier invoice tax must match the approved purchase order tax allocation for the invoiced quantities.']);
            }
            $grand = bcadd(bcsub($subtotal, $discount, 2), $tax, 2);
            if (bccomp($grand, '0', 2) <= 0) {
                throw ValidationException::withMessages(['grand_total' => 'Invoice total must be positive.']);
            }
            if (bccomp($grand, $expectedTotal, 2) !== 0) {
                throw ValidationException::withMessages(['grand_total' => 'Supplier invoice total must exactly match the approved purchase order tax/discount allocation for the invoiced quantities.']);
            }

            $invoiceNumber = trim((string) $data['invoice_number']);
            if ($invoiceNumber === '') {
                throw ValidationException::withMessages(['invoice_number' => 'Supplier invoice number is required.']);
            }

            // Different purchase orders can be invoiced concurrently for the
            // same supplier. Lock the shared supplier row before checking the
            // supplier-scoped invoice number; the unique index remains the
            // final database-level safeguard.
            $supplier = Supplier::query()->lockForUpdate()->findOrFail($po->supplier_id);
            $duplicateInvoice = SupplierInvoice::query()
                ->where('supplier_id', $supplier->id)
                ->where('invoice_number', $invoiceNumber)
                ->exists();
            if ($duplicateInvoice) {
                throw ValidationException::withMessages(['invoice_number' => 'This supplier invoice number has already been recorded.']);
            }

            $dueDate = $data['due_date'] ?? (
                (int) $supplier->credit_days > 0
                    ? Carbon::parse($data['invoice_date'])->addDays((int) $supplier->credit_days)->toDateString()
                    : null
            );
            $invoice = SupplierInvoice::query()->create([
                'supplier_id' => $po->supplier_id,
                'purchase_order_id' => $po->id,
                'invoice_number' => $invoiceNumber,
                'invoice_date' => $data['invoice_date'],
                'due_date' => $dueDate,
                'subtotal' => $subtotal,
                'tax_total' => $tax,
                'discount_total' => $discount,
                'grand_total' => $grand,
                'status' => 'draft',
                'created_by' => $actor->id,
            ]);
            foreach ($validatedLines as $line) {
                $invoice->items()->create($line);
            }
            $this->activityLog->recordForUser($actor, 'created', 'procurement', 'Recorded supplier invoice '.$invoice->invoice_number, $invoice);

            return $invoice->load(['supplier', 'purchaseOrder', 'items']);
        }, 3);
    }

    public function postSupplierInvoice(SupplierInvoice $supplierInvoice, User $actor): SupplierInvoice
    {
        return DB::transaction(function () use ($supplierInvoice, $actor): SupplierInvoice {
            $invoice = SupplierInvoice::query()->lockForUpdate()->findOrFail($supplierInvoice->id);
            if ($invoice->status !== 'draft') {
                throw ValidationException::withMessages(['invoice' => 'Only draft supplier invoices can be posted.']);
            }
            $grni = FinancialAccount::query()->where('code', '2050')->where('is_active', true)->firstOrFail();
            $payable = FinancialAccount::query()->where('code', '2000')->where('is_active', true)->firstOrFail();
            $journal = $this->ledger->createDraft([
                'source' => 'procurement_invoice',
                'entry_date' => $invoice->invoice_date->toDateString(),
                'description' => 'Supplier invoice '.$invoice->invoice_number,
                'reference' => $invoice->invoice_number,
                'location_id' => $invoice->purchaseOrder->location_id,
                'lines' => [
                    ['account_id' => $grni->id, 'debit' => $invoice->grand_total, 'credit' => '0', 'supplier_id' => $invoice->supplier_id],
                    ['account_id' => $payable->id, 'debit' => '0', 'credit' => $invoice->grand_total, 'supplier_id' => $invoice->supplier_id],
                ],
            ], $actor);
            $posted = $this->ledger->post($journal, $actor);
            $invoice->update(['status' => 'posted', 'journal_entry_id' => $posted->id, 'posted_at' => now()]);
            $this->activityLog->recordForUser($actor, 'posted', 'procurement', 'Posted supplier invoice '.$invoice->invoice_number, $invoice);

            return $invoice->fresh(['supplier', 'purchaseOrder', 'payments', 'items', 'journalEntry']);
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function paySupplierInvoice(SupplierInvoice $supplierInvoice, array $data, User $actor): SupplierPayment
    {
        return DB::transaction(function () use ($supplierInvoice, $data, $actor): SupplierPayment {
            $invoice = SupplierInvoice::query()->lockForUpdate()->with('purchaseOrder')->findOrFail($supplierInvoice->id);
            $key = trim((string) $data['idempotency_key']);
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::selectOne('SELECT pg_advisory_xact_lock(hashtext(?))', ['procurement-payment:'.$key]);
            }
            $hash = hash('sha256', json_encode(collect($data)->except('idempotency_key')->sortKeys()->all(), JSON_THROW_ON_ERROR));
            $existing = SupplierPayment::query()->where('idempotency_key', $key)->first();
            if ($existing) {
                if ($existing->supplier_invoice_id !== $invoice->id || ! hash_equals($existing->payload_hash, $hash)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This payment key has already been used with different data.']);
                }

                return $existing;
            }
            if (! in_array($invoice->status, ['posted', 'partially_paid'], true)) {
                throw ValidationException::withMessages(['invoice' => 'Only posted unpaid supplier invoices can be paid.']);
            }
            $amount = $this->money($data['amount']);
            $returnCredits = $this->postedSupplierReturnTotal($invoice);
            $due = bcsub(
                bcsub((string) $invoice->grand_total, (string) $invoice->paid_total, 2),
                $returnCredits,
                2,
            );
            if (bccomp($due, '0.00', 2) < 0) {
                $due = '0.00';
            }
            if (bccomp($amount, '0', 2) <= 0 || bccomp($amount, $due, 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'Payment must be positive and cannot exceed the net invoice outstanding balance after supplier return credits.']);
            }
            $cash = FinancialAccount::query()->where('is_active', true)->where('is_cash_bank', true)->lockForUpdate()->findOrFail((int) $data['cash_account_id']);
            $payable = FinancialAccount::query()->where('code', '2000')->where('is_active', true)->firstOrFail();
            $journal = $this->ledger->createDraft([
                'source' => 'procurement_payment',
                'entry_date' => $data['payment_date'],
                'description' => 'Supplier payment for invoice '.$invoice->invoice_number,
                'reference' => $data['reference'] ?? null,
                'location_id' => $invoice->purchaseOrder->location_id,
                'lines' => [
                    ['account_id' => $payable->id, 'debit' => $amount, 'credit' => '0', 'supplier_id' => $invoice->supplier_id],
                    ['account_id' => $cash->id, 'debit' => '0', 'credit' => $amount, 'supplier_id' => $invoice->supplier_id],
                ],
            ], $actor);
            $posted = $this->ledger->post($journal, $actor);
            $payment = SupplierPayment::query()->create([
                'payment_number' => $this->number('SPAY'),
                'supplier_invoice_id' => $invoice->id,
                'supplier_id' => $invoice->supplier_id,
                'location_id' => $invoice->purchaseOrder->location_id,
                'payment_date' => $data['payment_date'],
                'amount' => $amount,
                'payment_method' => $data['payment_method'],
                'cash_account_id' => $cash->id,
                'journal_entry_id' => $posted->id,
                'reference' => $data['reference'] ?? null,
                'idempotency_key' => $key,
                'payload_hash' => $hash,
                'created_by' => $actor->id,
            ]);
            $newPaid = bcadd((string) $invoice->paid_total, $amount, 2);
            $invoice->update(['paid_total' => $newPaid]);
            $this->syncSupplierInvoiceSettlementStatus($invoice);
            $this->activityLog->recordForUser($actor, 'paid', 'procurement', 'Recorded supplier payment '.$payment->payment_number, $payment);

            return $payment;
        }, 3);
    }

    /** @param array<string,mixed> $data */
    public function returnToSupplier(SupplierInvoice $supplierInvoice, array $data, User $actor): SupplierReturn
    {
        return DB::transaction(function () use ($supplierInvoice, $data, $actor): SupplierReturn {
            $invoice = SupplierInvoice::query()->lockForUpdate()->with(['purchaseOrder.items', 'items'])->findOrFail($supplierInvoice->id);
            $key = trim((string) ($data['idempotency_key'] ?? ''));
            if ($key === '') {
                throw ValidationException::withMessages(['idempotency_key' => 'A supplier return idempotency key is required.']);
            }
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::selectOne('SELECT pg_advisory_xact_lock(hashtext(?))', ['procurement-return:'.$key]);
            }
            $hash = hash('sha256', json_encode(collect($data)->except('idempotency_key')->sortKeys()->all(), JSON_THROW_ON_ERROR));
            $existingReturn = SupplierReturn::query()->where('idempotency_key', $key)->first();
            if ($existingReturn) {
                if ($existingReturn->supplier_invoice_id !== $invoice->id || ! hash_equals($existingReturn->payload_hash, $hash)) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This return key was already used with different data.']);
                }

                return $existingReturn->load('items');
            }
            if (! in_array($invoice->status, ['posted', 'partially_paid', 'paid'], true)) {
                throw ValidationException::withMessages(['invoice' => 'A supplier return requires a posted supplier invoice.']);
            }
            $po = $invoice->purchaseOrder;
            $inventory = FinancialAccount::query()->where('code', '1200')->where('is_active', true)->firstOrFail();
            $payable = FinancialAccount::query()->where('code', '2000')->where('is_active', true)->firstOrFail();
            $return = SupplierReturn::query()->create([
                'return_number' => $this->number('SRET'),
                'supplier_invoice_id' => $invoice->id,
                'supplier_id' => $invoice->supplier_id,
                'location_id' => $po->location_id,
                'return_date' => $data['return_date'] ?? now()->toDateString(),
                'total_amount' => '0.00',
                'status' => 'posted',
                'idempotency_key' => $key,
                'payload_hash' => $hash,
                'reason' => trim($data['reason']),
                'created_by' => $actor->id,
            ]);
            $total = '0.00';
            $returnQuantities = [];
            $returnLineValues = [];
            foreach ($data['items'] as $line) {
                $item = $po->items->firstWhere('id', (int) $line['purchase_order_item_id']);
                if (! $item) {
                    throw ValidationException::withMessages(['items' => 'Return item does not belong to the invoice purchase order.']);
                }
                $invoiceLine = $invoice->items->firstWhere('purchase_order_item_id', $item->id);
                if (! $invoiceLine) {
                    throw ValidationException::withMessages(['items' => 'The selected purchase order item is not included on this supplier invoice.']);
                }
                $qty = $this->quantity($line['quantity']);
                $previousValue = DB::table('supplier_return_items')
                    ->join('supplier_returns', 'supplier_returns.id', '=', 'supplier_return_items.supplier_return_id')
                    ->where('supplier_returns.supplier_invoice_id', $invoice->id)
                    ->where('supplier_return_items.purchase_order_item_id', $item->id)
                    ->sum('supplier_return_items.quantity');
                $previous = (string) $previousValue;
                $returnKey = (string) $item->id;
                $returnQuantities[$returnKey] = bcadd($returnQuantities[$returnKey] ?? '0.0000', $qty, 4);
                $available = bcsub((string) $invoiceLine->quantity, $previous, 4);
                if (bccomp($qty, '0', 4) <= 0 || bccomp($returnQuantities[$returnKey], $available, 4) > 0) {
                    throw ValidationException::withMessages(['items' => 'Return quantity exceeds invoiced quantity not already returned.']);
                }
                $alreadyAllocatedValue = DB::table('supplier_return_items')
                    ->join('supplier_returns', 'supplier_returns.id', '=', 'supplier_return_items.supplier_return_id')
                    ->where('supplier_returns.supplier_invoice_id', $invoice->id)
                    ->where('supplier_return_items.purchase_order_item_id', $item->id)
                    ->sum('supplier_return_items.line_total');
                $allocatedBefore = bcadd(
                    (string) $alreadyAllocatedValue,
                    $returnLineValues[$returnKey] ?? '0.00',
                    2,
                );
                $cumulativeReturned = bcadd($previous, $returnQuantities[$returnKey], 4);
                $cumulativeReturnValue = bcmul(
                    (string) $invoiceLine->line_total,
                    bcdiv($cumulativeReturned, (string) $invoiceLine->quantity, 8),
                    2,
                );
                $lineTotal = bcsub($cumulativeReturnValue, $allocatedBefore, 2);
                $returnLineValues[$returnKey] = bcadd($returnLineValues[$returnKey] ?? '0.00', $lineTotal, 2);
                $movement = $this->inventory->issue([
                    'location_id' => $po->location_id,
                    'product_id' => $item->product_id,
                    'product_variant_id' => $item->product_variant_id,
                    'quantity' => $qty,
                    'reason' => 'Supplier return '.$return->return_number,
                    'reference_type' => 'supplier_return',
                    'reference_id' => (string) $return->id,
                    'idempotency_key' => (string) Str::uuid(),
                ], $actor);
                $returnUnitCost = bcdiv($lineTotal, $qty, 6);
                $return->items()->create([
                    'purchase_order_item_id' => $item->id,
                    'quantity' => $qty,
                    'unit_cost' => $returnUnitCost,
                    'line_total' => $lineTotal,
                ]);
                $total = bcadd($total, $lineTotal, 2);
            }
            if (bccomp($total, '0', 2) <= 0) {
                throw ValidationException::withMessages(['items' => 'At least one positive supplier return line is required.']);
            }
            $priorCredits = (string) (SupplierReturn::query()->where('supplier_invoice_id', $invoice->id)->where('status', 'posted')->sum('total_amount'));
            if (bccomp(bcadd($priorCredits, $total, 2), (string) $invoice->grand_total, 2) > 0) {
                throw ValidationException::withMessages(['items' => 'Supplier return credit cannot exceed the invoice total.']);
            }
            // Inventory is relieved at its moving-average carrying value. The
            // supplier credit can differ (invoice discount/tax/price changes),
            // so post the difference to a dedicated purchase-price variance.
            $carryingValueRaw = InventoryValuationMovement::query()
                ->where('reference_type', 'supplier_return')
                ->where('reference_id', (string) $return->id)
                ->sum(DB::raw('ABS(value_delta)'));
            $carryingValue = bcadd((string) $carryingValueRaw, '0', 2);
            $variance = bcsub($total, $carryingValue, 2);
            $lines = [
                ['account_id' => $payable->id, 'debit' => $total, 'credit' => '0', 'supplier_id' => $invoice->supplier_id],
                ['account_id' => $inventory->id, 'debit' => '0', 'credit' => $carryingValue, 'supplier_id' => $invoice->supplier_id],
            ];
            if (bccomp($variance, '0', 2) > 0) {
                $varianceAccount = FinancialAccount::query()->where('code', '5100')->where('is_active', true)->firstOrFail();
                $lines[] = ['account_id' => $varianceAccount->id, 'debit' => $variance, 'credit' => '0'];
            } elseif (bccomp($variance, '0', 2) < 0) {
                $varianceAccount = FinancialAccount::query()->where('code', '5100')->where('is_active', true)->firstOrFail();
                $lines[] = ['account_id' => $varianceAccount->id, 'debit' => '0', 'credit' => bcmul($variance, '-1', 2)];
            }
            $journal = $this->ledger->createDraft([
                'source' => 'procurement_return',
                'entry_date' => $return->return_date->toDateString(),
                'description' => 'Supplier return '.$return->return_number,
                'reference' => $return->return_number,
                'location_id' => $po->location_id,
                'lines' => $lines,
            ], $actor);
            $posted = $this->ledger->post($journal, $actor);
            $return->update(['total_amount' => $total, 'journal_entry_id' => $posted->id]);
            $this->syncSupplierInvoiceSettlementStatus($invoice);
            $this->activityLog->recordForUser($actor, 'returned', 'procurement', 'Posted supplier return '.$return->return_number, $return);

            return $return->load(['items', 'journalEntry']);
        }, 3);
    }

    /** @return numeric-string */
    private function postedSupplierReturnTotal(SupplierInvoice $invoice): string
    {
        $total = SupplierReturn::query()
            ->where('supplier_invoice_id', $invoice->id)
            ->where('status', 'posted')
            ->sum('total_amount');

        return bcadd((string) $total, '0', 2);
    }

    private function syncSupplierInvoiceSettlementStatus(SupplierInvoice $invoice): void
    {
        $settledValue = bcadd((string) $invoice->paid_total, $this->postedSupplierReturnTotal($invoice), 2);
        $comparison = bccomp($settledValue, (string) $invoice->grand_total, 2);

        $status = $comparison >= 0
            ? 'paid'
            : (bccomp((string) $invoice->paid_total, '0.00', 2) > 0 ? 'partially_paid' : 'posted');

        if ($invoice->status !== $status) {
            $invoice->update(['status' => $status]);
        }
    }

    /** @return array{product_id:?int,variant_id:?int} */
    /** @param array<string, mixed> $item
     * @return array{product_id: ?int, variant_id: ?int}
     */
    private function validateOwner(array $item): array
    {
        $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;
        $variantId = isset($item['product_variant_id']) ? (int) $item['product_variant_id'] : null;
        if (($productId === null) === ($variantId === null)) {
            throw ValidationException::withMessages(['items' => 'Each line must specify exactly one product or product variant.']);
        }
        if ($productId !== null) {
            $product = Product::query()->where('status', 'active')->find($productId);
            if (! $product || ! $product->isSimple()) {
                throw ValidationException::withMessages(['items' => 'Select an active simple product.']);
            }
        } else {
            $variant = ProductVariant::query()->where('status', 'active')->find($variantId);
            if (! $variant || ! $variant->product()->where('status', 'active')->exists()) {
                throw ValidationException::withMessages(['items' => 'Select an active variant of an active product.']);
            }
        }

        return ['product_id' => $productId, 'variant_id' => $variantId];
    }

    /** @return numeric-string */
    private function quantity(mixed $value): string
    {
        $value = trim((string) $value);
        $this->assertValidQuantity($value);

        return bcadd($value, '0', 4);
    }

    /** @phpstan-assert numeric-string $value */
    private function assertValidQuantity(string $value): void
    {
        if (! preg_match('/^\d{1,14}(?:\.\d{1,4})?$/', $value)) {
            throw ValidationException::withMessages([
                'quantity' => 'Enter a positive quantity with up to four decimal places.',
            ]);
        }
    }

    /** @return numeric-string */
    private function money(mixed $value): string
    {
        $value = trim((string) $value);
        $this->assertValidMoney($value);

        return bcadd($value, '0', 2);
    }

    /** @phpstan-assert numeric-string $value */
    private function assertValidMoney(string $value): void
    {
        if (! preg_match('/^\d{1,14}(?:\.\d{1,2})?$/', $value)) {
            throw ValidationException::withMessages([
                'amount' => 'Enter a valid amount with up to two decimal places.',
            ]);
        }
    }

    private function number(string $prefix): string
    {
        return $prefix.'-'.now()->format('Ymd').'-'.strtoupper(Str::random(8));
    }
}
