<?php

namespace App\Services;

use App\Models\FinancialAccount;
use App\Models\InventoryStock;
use App\Models\InventoryValuationMovement;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\SalesReturn;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesReturnService
{
    public function __construct(
        private readonly FinancialLedgerService $ledger,
        private readonly LocationInventoryAccessService $locationAccess,
        private readonly ActivityLogService $activityLog,
    ) {}

    /**
     * @param array{
     *     sales_order_id: int,
     *     idempotency_key: string,
     *     return_date: string,
     *     refund_method: string,
     *     cash_account_id?: int|null,
     *     reason?: string|null,
     *     items: list<array{sales_order_item_id: int, quantity: string|int|float, disposition?: string, disposition_note?: string|null}>
     * } $data
     */
    public function create(array $data, User $actor): SalesReturn
    {
        return DB::transaction(function () use ($data, $actor): SalesReturn {
            $key = trim((string) $data['idempotency_key']);
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', ['madina-scale:sales-return:'.$key]);
            }
            $existing = SalesReturn::query()->where('idempotency_key', $key)->first();
            if ($existing) {
                $same = (int) $existing->sales_order_id === (int) $data['sales_order_id']
                    && $existing->refund_method === $data['refund_method']
                    && (string) $existing->return_date->toDateString() === (string) $data['return_date']
                    && $existing->reason === ($data['reason'] ?? null)
                    && (int) ($existing->cash_account_id ?? 0) === (int) ($data['cash_account_id'] ?? 0)
                    && collect($existing->items)->map(fn ($i) => [(int) $i->sales_order_item_id, (string) $i->quantity, $i->disposition ?? 'restock', $i->disposition_note])->sort()->values()->all()
                        === collect($data['items'])->map(fn ($i) => [(int) $i['sales_order_item_id'], number_format((float) $i['quantity'], 4, '.', ''), $i['disposition'] ?? 'restock', $i['disposition_note'] ?? null])->sort()->values()->all();
                if (! $same) {
                    throw ValidationException::withMessages(['idempotency_key' => 'This key was already used for a different return request.']);
                }

                return $existing->load(['items.orderItem', 'journalEntry']);
            }

            $order = SalesOrder::query()->whereKey($data['sales_order_id'])->lockForUpdate()->with(['items.reservation'])->firstOrFail();
            $this->locationAccess->assertManage($actor, (int) $order->location_id);
            if (! in_array($order->status, ['processing', 'completed'], true)) {
                throw ValidationException::withMessages(['sales_order_id' => 'Only processing or completed orders with fulfilled goods can be returned.']);
            }
            $this->locationAccess->assertManage($actor, (int) $order->location_id);

            $returnLines = [];
            $total = '0.00';
            foreach ($data['items'] as $requested) {
                $item = $order->items->firstWhere('id', (int) $requested['sales_order_item_id']);
                if (! $item) {
                    throw ValidationException::withMessages(['items' => 'A return item does not belong to this sales order.']);
                }
                $fulfilled = (string) $item->fulfillmentItems()->whereHas('fulfillment', fn ($q) => $q->where('status', 'completed'))->sum('quantity');
                $previouslyReturned = (string) DB::table('sales_return_items as sri')
                    ->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')
                    ->where('sri.sales_order_item_id', $item->id)->where('sr.status', 'completed')->sum('sri.quantity');
                $available = bcsub($fulfilled, $previouslyReturned, 4);
                $qty = number_format((float) $requested['quantity'], 4, '.', '');
                if (bccomp($qty, '0', 4) <= 0 || bccomp($qty, $available, 4) > 0) {
                    throw ValidationException::withMessages(['items' => 'Return quantity exceeds the fulfilled quantity that has not already been returned.']);
                }
                $baseAmount = bcmul(bcdiv((string) $item->line_total, (string) $item->quantity, 8), $qty, 2);
                $subtotal = (string) $order->subtotal;
                $allocationRatio = bccomp($subtotal, '0.00', 2) > 0 ? bcdiv($baseAmount, $subtotal, 8) : '0';
                $discountShare = bcmul((string) $order->discount_total, $allocationRatio, 2);
                $taxShare = bcmul((string) $order->tax_total, $allocationRatio, 2);
                $netAmount = bcsub($baseAmount, $discountShare, 2);
                if (bccomp($netAmount, '0.00', 2) < 0) {
                    throw ValidationException::withMessages(['items' => 'The return value is invalid after allocating the original order discount.']);
                }
                $lineAmount = bcadd($netAmount, $taxShare, 2);
                $total = bcadd($total, $lineAmount, 2);
                $disposition = $requested['disposition'] ?? 'restock';
                if (! in_array($disposition, ['restock', 'damaged', 'quarantine', 'rejected'], true)) {
                    throw ValidationException::withMessages(['items' => 'Choose a valid return disposition.']);
                }
                $returnLines[] = ['item' => $item, 'quantity' => $qty, 'unit_price' => (string) $item->unit_price, 'line_refund' => $lineAmount, 'net_amount' => $netAmount, 'tax_amount' => $taxShare, 'disposition' => $disposition, 'disposition_note' => $requested['disposition_note'] ?? null];
            }
            if (bccomp($total, '0.00', 2) <= 0) {
                throw ValidationException::withMessages(['items' => 'Return total must be greater than zero.']);
            }

            if ($data['refund_method'] === 'store_credit' && ! $order->customer_id) {
                throw ValidationException::withMessages(['refund_method' => 'Store credit requires the sales order to be linked to a customer.']);
            }
            $cash = null;
            if ($data['refund_method'] !== 'store_credit') {
                $cash = FinancialAccount::query()->where('is_active', true)->where('is_cash_bank', true)->lockForUpdate()->find($data['cash_account_id'] ?? null);
                if (! $cash) {
                    throw ValidationException::withMessages(['cash_account_id' => 'Choose an active cash, bank or mobile banking account for the refund.']);
                }
            }
            $return = SalesReturn::query()->create([
                'return_number' => 'SR-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'sales_order_id' => $order->id,
                'location_id' => $order->location_id,
                'customer_id' => $order->customer_id,
                'status' => 'completed',
                'refund_method' => $data['refund_method'],
                'cash_account_id' => $cash?->id,
                'refund_amount' => $total,
                'return_date' => $data['return_date'],
                'reason' => $data['reason'] ?? null,
                'idempotency_key' => $key,
                'created_by' => $actor->id,
            ]);

            foreach ($returnLines as $line) {
                /** @var SalesOrderItem $item */
                $item = $line['item'];
                $return->items()->create([
                    'sales_order_item_id' => $item->id,
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_refund' => $line['line_refund'],
                    'disposition' => $line['disposition'],
                    'disposition_note' => $line['disposition_note'],
                ]);

                // Only inspected, saleable goods may increase sellable inventory.
                // Damaged, quarantined and rejected quantities remain recorded on the
                // return item but are deliberately excluded from available stock.
                if ($line['disposition'] !== 'restock') {
                    continue;
                }
                $reservation = $item->reservation;
                $owner = $reservation && $reservation->product_id
                    ? ['product_id' => (int) $reservation->product_id, 'product_variant_id' => null]
                    : ['product_id' => null, 'product_variant_id' => (int) $item->product_variant_id];

                // Match InventoryService's lock order: owner first, then location,
                // then stock row. This also serializes creation when no stock row exists.
                if ($owner['product_id'] !== null) {
                    $product = Product::query()->lockForUpdate()->findOrFail($owner['product_id']);
                    if (! $product->isSimple()) {
                        throw ValidationException::withMessages(['items' => 'The returned product is no longer a simple stock owner.']);
                    }
                } else {
                    // Lock parent before variant, matching reservation and inventory mutations.
                    $variantReference = ProductVariant::query()
                        ->whereKey($owner['product_variant_id'])
                        ->first(['id', 'product_id']);
                    if (! $variantReference) {
                        throw ValidationException::withMessages(['items' => 'The returned variant no longer exists.']);
                    }
                    $product = Product::query()->lockForUpdate()->findOrFail($variantReference->product_id);
                    $variant = ProductVariant::query()
                        ->whereKey($owner['product_variant_id'])
                        ->where('product_id', $product->id)
                        ->lockForUpdate()
                        ->first();
                    if (! $product->isVariable() || ! $variant || $variant->trashed()) {
                        throw ValidationException::withMessages(['items' => 'The returned variant is no longer available.']);
                    }
                }
                Location::query()->lockForUpdate()->findOrFail($order->location_id);

                $stockQuery = InventoryStock::query()->where('location_id', $order->location_id);
                $owner['product_id'] !== null
                    ? $stockQuery->where('product_id', $owner['product_id'])->whereNull('product_variant_id')
                    : $stockQuery->where('product_variant_id', $owner['product_variant_id'])->whereNull('product_id');
                $stock = $stockQuery->lockForUpdate()->first();
                if (! $stock) {
                    $stock = InventoryStock::query()->create($owner + ['location_id' => $order->location_id, 'quantity' => '0.0000', 'reserved_quantity' => '0.0000']);
                }
                $before = (string) $stock->quantity;
                $after = bcadd($before, $line['quantity'], 4);
                $stock->quantity = $after;
                $stock->save();
                $movement = StockMovement::query()->create([
                    'product_id' => $owner['product_id'], 'product_variant_id' => $owner['product_variant_id'],
                    'location_id' => $order->location_id, 'movement_type' => 'receipt',
                    'quantity_delta' => $line['quantity'], 'balance_before' => $before, 'balance_after' => $after,
                    'reason' => 'Sales return '.$return->return_number,
                    'reference_type' => SalesReturn::class, 'reference_id' => (string) $return->id,
                    'idempotency_key' => (string) Str::uuid(), 'created_by' => $actor->id,
                    'metadata' => ['sales_order_id' => $order->id, 'sales_order_item_id' => $item->id, 'sales_return_id' => $return->id],
                ]);
                $originalCostQuantity = (string) ($item->fulfilled_cost_quantity ?? '0');
                $hasOriginalCost = bccomp($originalCostQuantity, '0', 4) > 0;
                $returnUnitCost = $hasOriginalCost
                    ? bcdiv((string) $item->fulfilled_cost_value, $originalCostQuantity, 6)
                    : (string) ($stock->average_unit_cost ?? '0');
                app(InventoryValuationService::class)->apply(
                    $stock,
                    $movement,
                    $returnUnitCost,
                    $actor,
                    null,
                    ['sales_return_id' => $return->id, 'cost_basis' => $hasOriginalCost ? 'original_fulfillment_cogs' : 'current_moving_average_legacy_fallback'],
                );
            }

            $returnAccount = FinancialAccount::query()->where('code', '4050')->where('is_active', true)->firstOrFail();
            $netRefund = '0.00';
            $taxRefund = '0.00';
            foreach ($returnLines as $line) {
                $netRefund = bcadd($netRefund, $line['net_amount'], 2);
                $taxRefund = bcadd($taxRefund, $line['tax_amount'], 2);
            }
            $lines = [];
            if (bccomp($netRefund, '0.00', 2) > 0) {
                $lines[] = ['account_id' => $returnAccount->id, 'debit' => $netRefund, 'credit' => '0.00', 'customer_id' => $order->customer_id];
            }
            if (bccomp($taxRefund, '0.00', 2) > 0) {
                $taxPayable = FinancialAccount::query()->where('code', '2200')->where('is_active', true)->firstOrFail();
                $lines[] = ['account_id' => $taxPayable->id, 'debit' => $taxRefund, 'credit' => '0.00', 'customer_id' => $order->customer_id];
            }
            if ($data['refund_method'] === 'store_credit') {
                $advance = FinancialAccount::query()->where('code', '2100')->where('is_active', true)->firstOrFail();
                $lines[] = ['account_id' => $advance->id, 'debit' => '0.00', 'credit' => $total, 'customer_id' => $order->customer_id];
            } else {
                $lines[] = ['account_id' => $cash->id, 'debit' => '0.00', 'credit' => $total, 'customer_id' => $order->customer_id];
            }
            $restockedCarryingValue = (string) InventoryValuationMovement::query()
                ->where('reference_type', SalesReturn::class)
                ->where('reference_id', (string) $return->id)
                ->where('value_delta', '>', 0)
                ->sum('value_delta');
            if (bccomp($restockedCarryingValue, '0', 2) > 0) {
                $inventoryAccount = FinancialAccount::query()->where('code', '1200')->where('is_active', true)->firstOrFail();
                $costOfGoodsSold = FinancialAccount::query()->where('code', '5000')->where('is_active', true)->firstOrFail();
                $lines[] = ['account_id' => $inventoryAccount->id, 'debit' => $restockedCarryingValue, 'credit' => '0.00'];
                $lines[] = ['account_id' => $costOfGoodsSold->id, 'debit' => '0.00', 'credit' => $restockedCarryingValue];
            }
            $journal = $this->ledger->createDraft([
                'entry_date' => $data['return_date'],
                'description' => 'Sales return '.$return->return_number.' for '.$order->order_number,
                'reference' => $return->return_number,
                'location_id' => $order->location_id,
                'lines' => $lines,
            ], $actor);
            $this->ledger->post($journal, $actor);
            $return->update(['journal_entry_id' => $journal->id]);
            $this->activityLog->recordForUser($actor, 'completed', 'sales_returns', 'Completed sales return '.$return->return_number, $return, null, [
                'sales_order_id' => $order->id, 'refund_amount' => $total, 'refund_method' => $return->refund_method,
                'journal_entry_id' => $journal->id, 'item_count' => count($returnLines),
                'disposition_totals' => collect($returnLines)->groupBy('disposition')->map(fn ($items) => $items->sum(fn ($item) => (float) $item['quantity']))->all(),
            ]);

            return $return->fresh(['items.orderItem', 'journalEntry']);
        }, 3);
    }
}
