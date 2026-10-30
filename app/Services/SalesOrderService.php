<?php

namespace App\Services;

use App\Models\B2BAccount;
use App\Models\SalesOrderFlashSaleAllocation;
use App\Models\InventoryReservation;
use App\Models\InventoryReservationPurpose;
use App\Models\InventoryStock;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\SalesOrder;
use App\Models\SalesOrderFulfillment;
use App\Models\SalesOrderFulfillmentItem;
use App\Models\SalesOrderItem;
use App\Models\SalesOrderStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SalesOrderService
{
    public function __construct(
        private readonly InventoryReservationService $reservations,
        private readonly OrderRiskService $risk,
        private readonly CommercialPricingService $commercialPricing,
        private readonly B2BCreditService $b2bCredit,
    ) {}

    /** Resolve the actual inventory owner used by this location for an order line. */
    private function reservationOwner(ProductVariant $variant, int $locationId): array
    {
        $variantStockExists = InventoryStock::query()
            ->where('location_id', $locationId)
            ->where('product_variant_id', $variant->id)
            ->exists();
        if ($variantStockExists || $variant->product?->product_type !== 'simple') {
            return ['product_variant_id' => $variant->id];
        }

        $productStockExists = InventoryStock::query()
            ->where('location_id', $locationId)
            ->where('product_id', $variant->product_id)
            ->whereNull('product_variant_id')
            ->exists();

        return $productStockExists
            ? ['product_id' => $variant->product_id]
            : ['product_variant_id' => $variant->id];
    }

    public function create(array $data, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($data, $actor): SalesOrder {
            if (! empty($data['idempotency_key'])) {
                // PostgreSQL transaction-scoped advisory lock closes the race
                // where identical keys arrive at different locations and thus
                // do not contend on the same location row.
                if (DB::connection()->getDriverName() === 'pgsql') {
                    DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))', [
                        (string) $data['idempotency_key'],
                    ]);
                }

                $existing = SalesOrder::query()->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    if ((int) $existing->created_by !== (int) $actor->id) {
                        throw ValidationException::withMessages(['idempotency_key' => 'This request key has already been used. Start a new order request.']);
                    }

                    $existing->load(['items', 'location', 'creator']);
                    $this->assertIdempotentReplayMatches($existing, $data);

                    return $existing;
                }
            }

            $location = Location::query()->lockForUpdate()->find($data['location_id']);
            if (! $location || ! $location->is_active) {
                throw ValidationException::withMessages(['location_id' => 'Choose an active location.']);
            }
            $purpose = InventoryReservationPurpose::query()->where('key', 'customer_order')->where('is_active', true)->first();
            if (! $purpose) {
                throw ValidationException::withMessages(['items' => 'The active “Customer order” reservation purpose is required before creating orders.']);
            }

            $preserveQuotePricing = (bool) ($data['preserve_quote_pricing'] ?? false);
            $b2bAccount = ! empty($data['b2b_account_id'])
                ? B2BAccount::query()->whereKey($data['b2b_account_id'])->lockForUpdate()->firstOrFail()
                : (! empty($data['customer_id'])
                    ? B2BAccount::query()->where('customer_id', $data['customer_id'])->lockForUpdate()->first()
                    : null);
            if ($b2bAccount && $b2bAccount->status !== 'active') {
                throw ValidationException::withMessages(['account' => 'The linked business account is not active and cannot place orders.']);
            }

            $order = SalesOrder::query()->create([
                'order_number' => 'MS-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'status' => 'pending',
                'channel' => $data['channel'] ?? 'backoffice',
                'location_id' => $location->id,
                'customer_id' => $data['customer_id'] ?? null,
                'b2b_account_id' => $b2bAccount?->id,
                'customer_name' => trim($data['customer_name']),
                'customer_email' => isset($data['customer_email']) ? trim($data['customer_email']) : null,
                'customer_phone' => isset($data['customer_phone']) ? trim($data['customer_phone']) : null,
                'currency' => 'BDT',
                'subtotal' => 0,
                'discount_total' => 0,
                'tax_total' => 0,
                'grand_total' => 0,
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            $subtotal = '0.00';
            foreach ($data['items'] as $line) {
                // Price is always sourced from the active catalog, never trusted from the client.
                $variant = ProductVariant::query()->with('product')->lockForUpdate()->find($line['product_variant_id']);
                if (! $variant || $variant->status !== 'active' || ! $variant->product || $variant->product->status !== 'active') {
                    throw ValidationException::withMessages(['items' => 'One of the selected products is no longer available for sale.']);
                }
                $qty = number_format((float) $line['quantity'], 4, '.', '');
                $resolvedPrice = $preserveQuotePricing
                    ? ['unit_price' => $variant->effectiveSellingPrice(), 'catalogue_price' => $variant->effectiveSellingPrice(), 'price_source' => 'b2b_quote_pending', 'flash_sale_item' => null, 'flash_sale_regular_price' => null]
                    : $this->commercialPricing->priceLine($variant, $qty, $b2bAccount);
                $unitPrice = $resolvedPrice['unit_price'];
                if (bccomp($unitPrice, '0', 2) < 0) {
                    throw ValidationException::withMessages(['items' => 'Product price must not be negative.']);
                }
                $lineTotal = bcadd('0', bcmul($qty, $unitPrice, 2), 2);
                $subtotal = bcadd($subtotal, $lineTotal, 2);

                $item = SalesOrderItem::query()->create([
                    'sales_order_id' => $order->id,
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku ?: $variant->product->sku,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'pricing_snapshot' => [
                        'catalogue_price' => $resolvedPrice['catalogue_price'],
                        'price_source' => $resolvedPrice['price_source'],
                        'flash_sale_item_id' => $resolvedPrice['flash_sale_item']?->id,
                        'flash_sale_regular_price' => $resolvedPrice['flash_sale_regular_price'],
                        'line_discount' => '0.00',
                    ],
                ]);

                if ($resolvedPrice['flash_sale_item']) {
                    SalesOrderFlashSaleAllocation::query()->create([
                        'flash_sale_item_id' => $resolvedPrice['flash_sale_item']->id,
                        'sales_order_id' => $order->id,
                        'sales_order_item_id' => $item->id,
                        'quantity' => (int) $qty,
                        'regular_unit_price' => $resolvedPrice['flash_sale_regular_price'],
                        'sale_unit_price' => $unitPrice,
                        'status' => 'reserved',
                    ]);
                }

                $reservation = $this->reservations->reserve(array_merge(
                    $this->reservationOwner($variant, (int) $location->id), [
                    'location_id' => $location->id,
                    'quantity' => $qty,
                    'purpose' => $purpose->key,
                    'reference_type' => 'sales_order',
                    'reference_id' => (string) $order->id,
                    'reason' => 'Sales order '.$order->order_number,
                    'expires_at' => now()->addHours(72),
                ]), $actor);
                $item->reservation_id = $reservation->id;
                $item->save();
            }

            $pricedLines = [];
            foreach ($order->items()->with('variant.product')->orderBy('id')->get() as $orderItem) {
                $snapshot = $orderItem->pricing_snapshot ?? [];
                $pricedLines[] = [
                    'item' => $orderItem,
                    'variant' => $orderItem->variant,
                    'gross' => bcmul((string) $orderItem->quantity, (string) $orderItem->unit_price, 2),
                    'snapshot' => $snapshot,
                ];
            }
            $promotionResult = $preserveQuotePricing
                ? ['discount_total' => '0.00', 'order_discount' => '0.00', 'promotion_id' => null, 'promotion_code' => null, 'applied_promotions' => []]
                : $this->commercialPricing->applyPromotions($order, $pricedLines, $data['customer_id'] ?? null, $data['promotion_code'] ?? null);

            $manualDiscount = $preserveQuotePricing
                ? '0.00'
                : number_format((float) ($data['discount_total'] ?? 0), 2, '.', '');
            $discountTotal = bcadd($promotionResult['discount_total'], $manualDiscount, 2);
            if (bccomp($discountTotal, '0.00', 2) < 0 || bccomp($discountTotal, $subtotal, 2) > 0) {
                throw ValidationException::withMessages(['discount_total' => 'Discount must be between BDT 0.00 and the order subtotal.']);
            }
            $order->subtotal = $subtotal;
            $order->discount_total = $discountTotal;
            $order->grand_total = bcsub($subtotal, $discountTotal, 2);
            $order->promotion_id = $promotionResult['promotion_id'];
            $order->promotion_code = $promotionResult['promotion_code'];
            $order->pricing_snapshot = [
                'engine' => 'commercial_pricing_v1',
                'price_basis' => $preserveQuotePricing ? 'b2b_quote' : 'catalogue_and_commercial_rules',
                'promotion_code' => $promotionResult['promotion_code'],
                'applied_promotions' => $promotionResult['applied_promotions'] ?? [],
                'promotion_discount' => $promotionResult['discount_total'],
                'manual_discount' => $manualDiscount,
                'total_discount' => $discountTotal,
                'calculated_at' => now()->toIso8601String(),
            ];
            if ($b2bAccount && ! $preserveQuotePricing) {
                $creditSnapshot = $this->b2bCredit->assess($b2bAccount, (string) $order->grand_total, (int) $order->id);
                $order->credit_snapshot = $creditSnapshot;
                $order->payment_due_at = $creditSnapshot['payment_due_at'];
                $order->save();
            } else {
                $order->save();
            }

            SalesOrderStatusHistory::query()->create([
                'sales_order_id' => $order->id, 'from_status' => null, 'to_status' => 'pending',
                'reason' => 'Order created and inventory reserved.', 'metadata' => ['item_count' => count($data['items'])],
                'changed_by' => $actor->id, 'created_at' => now(),
            ]);

            $this->risk->assess($order);

            return $order->load(['items', 'location', 'creator', 'statusHistory.actor', 'riskAssessments']);
        }, 3);
    }

    /**
     * An idempotency key may replay only the same logical request. Returning an
     * old order for a changed cart/customer/location silently hides client bugs
     * and can make a retry appear to have succeeded with different data.
     */
    private function assertIdempotentReplayMatches(SalesOrder $existing, array $data): void
    {
        $normalizeText = static function ($value): ?string {
            $value = trim((string) ($value ?? ''));

            return $value === '' ? null : $value;
        };

        $requestedItems = collect($data['items'] ?? [])
            ->map(fn (array $item): array => [
                'variant_id' => (int) $item['product_variant_id'],
                'quantity' => number_format((float) $item['quantity'], 4, '.', ''),
            ])
            ->sortBy(fn (array $item): string => str_pad((string) $item['variant_id'], 20, '0', STR_PAD_LEFT))
            ->values()
            ->all();

        $existingItems = $existing->items
            ->map(fn (SalesOrderItem $item): array => [
                'variant_id' => (int) $item->product_variant_id,
                'quantity' => number_format((float) $item->quantity, 4, '.', ''),
            ])
            ->sortBy(fn (array $item): string => str_pad((string) $item['variant_id'], 20, '0', STR_PAD_LEFT))
            ->values()
            ->all();

        $snapshot = $existing->pricing_snapshot ?? [];
        $requestedManualDiscount = number_format((float) ($data['discount_total'] ?? 0), 2, '.', '');
        $existingManualDiscount = number_format((float) ($snapshot['manual_discount'] ?? 0), 2, '.', '');
        $requestedPromotionCode = strtoupper(trim((string) ($data['promotion_code'] ?? '')));
        $existingPromotionCode = strtoupper(trim((string) ($existing->promotion_code ?? $snapshot['promotion_code'] ?? '')));

        $matches = (int) $existing->location_id === (int) ($data['location_id'] ?? 0)
            && (int) ($existing->customer_id ?? 0) === (int) ($data['customer_id'] ?? 0)
            && $normalizeText($existing->customer_name) === $normalizeText($data['customer_name'] ?? null)
            && $normalizeText($existing->customer_email) === $normalizeText($data['customer_email'] ?? null)
            && $normalizeText($existing->customer_phone) === $normalizeText($data['customer_phone'] ?? null)
            && (string) $existing->channel === (string) ($data['channel'] ?? 'backoffice')
            && $normalizeText($existing->notes) === $normalizeText($data['notes'] ?? null)
            && $requestedItems === $existingItems
            && $requestedManualDiscount === $existingManualDiscount
            && $requestedPromotionCode === $existingPromotionCode;

        if (! $matches) {
            throw ValidationException::withMessages([
                'idempotency_key' => 'This request key was already used for a different order payload. Use a new key for changed order details.',
            ]);
        }
    }

    /**
     * Update a pending order while preserving atomic stock reservation semantics.
     * Confirmed or partially fulfilled orders are immutable through this endpoint.
     */
    public function updatePending(SalesOrder $order, array $data, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($order, $data, $actor): SalesOrder {
            $locked = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages([
                    'status' => 'Only pending orders can be edited. Cancel and recreate the order if changes are required after confirmation.',
                ]);
            }
            if ($locked->b2b_quote_id !== null) {
                throw ValidationException::withMessages([
                    'status' => 'An order converted from an accepted B2B quotation is commercially locked and cannot be edited.',
                ]);
            }

            $location = Location::query()->lockForUpdate()->find($locked->location_id);
            if (! $location || ! $location->is_active) {
                throw ValidationException::withMessages(['location_id' => 'The order location is no longer active.']);
            }

            $purpose = InventoryReservationPurpose::query()
                ->where('key', 'customer_order')
                ->where('is_active', true)
                ->first();

            if (! $purpose) {
                throw ValidationException::withMessages(['items' => 'The active “Customer order” reservation purpose is required before editing orders.']);
            }

            // Release commercial quota/redemptions and stock reservations atomically.
            // If the replacement cart fails, the transaction restores the previous state.
            $this->commercialPricing->releaseOrder($locked);

            // Release this order's current active reservations first. If the new cart
            // cannot be reserved, the surrounding transaction rolls these changes back.
            $reservations = InventoryReservation::query()
                ->where('reference_type', 'sales_order')
                ->where('reference_id', (string) $locked->id)
                ->where('status', 'active')
                ->lockForUpdate()
                ->get();

            foreach ($reservations as $reservation) {
                $this->reservations->resolve($reservation, 'cancel', $actor);
            }

            $locked->items()->delete();

            $b2bAccount = ! empty($data['customer_id'])
                ? B2BAccount::query()->where('customer_id', $data['customer_id'])->lockForUpdate()->first()
                : null;
            if ($b2bAccount && $b2bAccount->status !== 'active') {
                throw ValidationException::withMessages(['account' => 'The linked business account is not active and cannot place orders.']);
            }
            $subtotal = '0.00';
            foreach ($data['items'] as $line) {
                $variant = ProductVariant::query()
                    ->with('product')
                    ->lockForUpdate()
                    ->find($line['product_variant_id']);

                if (! $variant || $variant->status !== 'active' || ! $variant->product || $variant->product->status !== 'active') {
                    throw ValidationException::withMessages(['items' => 'One of the selected products is no longer available for sale.']);
                }

                $qty = number_format((float) $line['quantity'], 4, '.', '');
                $resolvedPrice = $this->commercialPricing->priceLine($variant, $qty, $b2bAccount);
                $unitPrice = $resolvedPrice['unit_price'];

                if (bccomp($unitPrice, '0', 2) < 0) {
                    throw ValidationException::withMessages(['items' => 'Product price must not be negative.']);
                }

                $lineTotal = bcadd('0', bcmul($qty, $unitPrice, 2), 2);
                $subtotal = bcadd($subtotal, $lineTotal, 2);

                $item = SalesOrderItem::query()->create([
                    'sales_order_id' => $locked->id,
                    'product_id' => $variant->product_id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $variant->product->name,
                    'variant_name' => $variant->name,
                    'sku' => $variant->sku ?: $variant->product->sku,
                    'quantity' => $qty,
                    'unit_price' => $unitPrice,
                    'line_total' => $lineTotal,
                    'pricing_snapshot' => [
                        'catalogue_price' => $resolvedPrice['catalogue_price'],
                        'price_source' => $resolvedPrice['price_source'],
                        'flash_sale_item_id' => $resolvedPrice['flash_sale_item']?->id,
                        'flash_sale_regular_price' => $resolvedPrice['flash_sale_regular_price'],
                        'line_discount' => '0.00',
                    ],
                ]);

                if ($resolvedPrice['flash_sale_item']) {
                    SalesOrderFlashSaleAllocation::query()->create([
                        'flash_sale_item_id' => $resolvedPrice['flash_sale_item']->id,
                        'sales_order_id' => $locked->id,
                        'sales_order_item_id' => $item->id,
                        'quantity' => (int) $qty,
                        'regular_unit_price' => $resolvedPrice['flash_sale_regular_price'],
                        'sale_unit_price' => $unitPrice,
                        'status' => 'reserved',
                    ]);
                }

                $reservation = $this->reservations->reserve(array_merge(
                    $this->reservationOwner($variant, (int) $location->id), [
                    'location_id' => $location->id,
                    'quantity' => $qty,
                    'purpose' => $purpose->key,
                    'reference_type' => 'sales_order',
                    'reference_id' => (string) $locked->id,
                    'reason' => 'Sales order '.$locked->order_number.' (edited)',
                    'expires_at' => now()->addHours(72),
                ]), $actor);

                $item->reservation_id = $reservation->id;
                $item->save();
            }

            $locked->customer_id = $data['customer_id'] ?? null;
            $locked->customer_name = trim($data['customer_name']);
            $locked->customer_email = isset($data['customer_email']) && trim((string) $data['customer_email']) !== ''
                ? trim((string) $data['customer_email'])
                : null;
            $locked->customer_phone = isset($data['customer_phone']) && trim((string) $data['customer_phone']) !== ''
                ? trim((string) $data['customer_phone'])
                : null;
            $locked->notes = isset($data['notes']) && trim((string) $data['notes']) !== ''
                ? trim((string) $data['notes'])
                : null;
            $pricedLines = [];
            foreach ($locked->items()->with('variant.product')->orderBy('id')->get() as $orderItem) {
                $pricedLines[] = [
                    'item' => $orderItem, 'variant' => $orderItem->variant,
                    'gross' => bcmul((string) $orderItem->quantity, (string) $orderItem->unit_price, 2),
                    'snapshot' => $orderItem->pricing_snapshot ?? [],
                ];
            }
            $promotionResult = $this->commercialPricing->applyPromotions($locked, $pricedLines, $data['customer_id'] ?? null, $data['promotion_code'] ?? null);
            $manualDiscount = number_format((float) ($data['discount_total'] ?? 0), 2, '.', '');
            $discountTotal = bcadd($promotionResult['discount_total'], $manualDiscount, 2);
            if (bccomp($discountTotal, '0.00', 2) < 0 || bccomp($discountTotal, $subtotal, 2) > 0) {
                throw ValidationException::withMessages(['discount_total' => 'Discount must be between BDT 0.00 and the order subtotal.']);
            }

            $locked->subtotal = $subtotal;
            $locked->discount_total = $discountTotal;
            $locked->promotion_id = $promotionResult['promotion_id'];
            $locked->promotion_code = $promotionResult['promotion_code'];
            $locked->tax_total = '0.00';
            $locked->grand_total = bcsub($subtotal, $discountTotal, 2);
            $locked->b2b_account_id = $b2bAccount?->id;
            if ($b2bAccount) {
                $creditSnapshot = $this->b2bCredit->assess($b2bAccount, (string) $locked->grand_total, (int) $locked->id);
                $locked->credit_snapshot = $creditSnapshot;
                $locked->payment_due_at = $creditSnapshot['payment_due_at'];
            } else {
                $locked->credit_snapshot = null;
                $locked->payment_due_at = null;
            }
            $locked->pricing_snapshot = [
                'engine' => 'commercial_pricing_v1',
                'price_basis' => 'catalogue_and_commercial_rules',
                'promotion_code' => $promotionResult['promotion_code'],
                'applied_promotions' => $promotionResult['applied_promotions'] ?? [],
                'promotion_discount' => $promotionResult['discount_total'],
                'manual_discount' => $manualDiscount,
                'total_discount' => $discountTotal,
                'calculated_at' => now()->toIso8601String(),
            ];
            $locked->save();

            SalesOrderStatusHistory::query()->create([
                'sales_order_id' => $locked->id,
                'from_status' => 'pending',
                'to_status' => 'pending',
                'reason' => 'Pending order details and items updated.',
                'metadata' => [
                    'item_count' => count($data['items']),
                    'released_reservations' => $reservations->count(),
                    'updated_fields' => ['customer_name', 'customer_email', 'customer_phone', 'notes', 'items', 'totals'],
                ],
                'changed_by' => $actor->id,
                'created_at' => now(),
            ]);

            $this->risk->assess($locked);

            return $locked->load(['items', 'location', 'creator', 'statusHistory.actor', 'riskAssessments']);
        }, 3);
    }

    public function cancel(SalesOrder $order, User $actor, string $reason): SalesOrder
    {
        return DB::transaction(function () use ($order, $actor, $reason): SalesOrder {
            $locked = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status === 'cancelled') {
                return $locked->load(['items', 'location', 'statusHistory.actor']);
            }
            if (! in_array($locked->status, ['pending', 'confirmed'], true)) {
                throw ValidationException::withMessages(['status' => 'Only pending or confirmed orders can be cancelled.']);
            }

            $this->commercialPricing->releaseOrder($locked);

            $reservations = InventoryReservation::query()
                ->where('reference_type', 'sales_order')
                ->where('reference_id', (string) $locked->id)
                ->where('status', 'active')->lockForUpdate()->get();

            foreach ($reservations as $reservation) {
                $this->reservations->resolve($reservation, 'cancel', $actor);
            }

            $from = $locked->status;
            $locked->status = 'cancelled';
            $locked->cancelled_at = now();
            $locked->cancellation_reason = $reason;
            $locked->save();

            SalesOrderStatusHistory::query()->create([
                'sales_order_id' => $locked->id, 'from_status' => $from, 'to_status' => 'cancelled',
                'reason' => $reason, 'metadata' => ['released_reservations' => $reservations->count()],
                'changed_by' => $actor->id, 'created_at' => now(),
            ]);

            return $locked->load(['items', 'location', 'statusHistory.actor']);
        }, 3);
    }

    public function startProcessing(SalesOrder $order, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($order, $actor): SalesOrder {
            $locked = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);

            if ($locked->status === 'processing') {
                return $locked->load(['items', 'location', 'statusHistory.actor']);
            }

            if ($locked->status !== 'confirmed') {
                throw ValidationException::withMessages([
                    'status' => 'Only confirmed orders can move into processing.',
                ]);
            }

            $activeCount = InventoryReservation::query()
                ->where('reference_type', 'sales_order')
                ->where('reference_id', (string) $locked->id)
                ->where('status', 'active')
                ->count();

            if ($activeCount !== $locked->items()->count()) {
                throw ValidationException::withMessages([
                    'status' => 'One or more stock reservations have expired or been released. Review the order before processing.',
                ]);
            }

            $locked->status = 'processing';
            $locked->save();

            SalesOrderStatusHistory::query()->create([
                'sales_order_id' => $locked->id,
                'from_status' => 'confirmed',
                'to_status' => 'processing',
                'reason' => 'Order moved to processing.',
                'changed_by' => $actor->id,
                'created_at' => now(),
            ]);

            return $locked->load(['items', 'location', 'statusHistory.actor']);
        }, 3);
    }

    public function fulfill(SalesOrder $order, array $data, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($order, $data, $actor): SalesOrder {
            $locked = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);
            if (! in_array($locked->status, ['confirmed', 'processing', 'partially_fulfilled'], true)) {
                throw ValidationException::withMessages(['status' => 'Only confirmed orders with an active workflow can be fulfilled.']);
            }

            if (! empty($data['idempotency_key'])) {
                $existing = SalesOrderFulfillment::query()->where('idempotency_key', $data['idempotency_key'])->first();
                if ($existing) {
                    if ((int) $existing->sales_order_id !== (int) $locked->id) {
                        throw ValidationException::withMessages(['idempotency_key' => 'This request key has already been used for another fulfillment.']);
                    }

                    return $locked->load(['items', 'location', 'statusHistory.actor', 'fulfillments.items']);
                }
            }

            $items = SalesOrderItem::query()->where('sales_order_id', $locked->id)->lockForUpdate()->get()->keyBy('id');
            $requested = collect($data['items'])->keyBy(fn (array $line): int => (int) $line['sales_order_item_id']);
            $fulfillment = SalesOrderFulfillment::query()->create([
                'fulfillment_number' => 'FUL-'.now()->format('ymd').'-'.Str::upper(Str::random(8)),
                'sales_order_id' => $locked->id,
                'status' => 'completed',
                'notes' => $data['notes'] ?? null,
                'created_by' => $actor->id,
                'fulfilled_at' => now(),
                'idempotency_key' => $data['idempotency_key'] ?? null,
            ]);

            foreach ($requested as $itemId => $line) {
                /** @var SalesOrderItem|null $item */
                $item = $items->get((int) $itemId);
                if (! $item) {
                    throw ValidationException::withMessages(['items' => 'A selected item does not belong to this order.']);
                }
                $qty = number_format((float) $line['quantity'], 4, '.', '');
                $alreadyFulfilled = SalesOrderFulfillmentItem::query()
                    ->where('sales_order_item_id', $item->id)->sum('quantity');
                $remaining = bcsub((string) $item->quantity, (string) $alreadyFulfilled, 4);
                if (bccomp($qty, '0', 4) <= 0 || bccomp($qty, $remaining, 4) > 0) {
                    throw ValidationException::withMessages(['items' => "Fulfillment quantity for {$item->product_name} exceeds the remaining quantity ({$remaining})."]);
                }
                if (! $item->reservation_id) {
                    throw ValidationException::withMessages(['items' => 'An order item is missing its stock reservation.']);
                }
                $reservation = InventoryReservation::query()->lockForUpdate()->find($item->reservation_id);
                if (! $reservation || $reservation->status !== 'active') {
                    throw ValidationException::withMessages(['items' => 'A stock reservation is no longer active. Review or re-reserve stock before fulfillment.']);
                }
                $this->reservations->fulfillPartial($reservation, $qty, $actor);
                SalesOrderFulfillmentItem::query()->create([
                    'sales_order_fulfillment_id' => $fulfillment->id,
                    'sales_order_item_id' => $item->id,
                    'quantity' => $qty,
                ]);
            }

            $allFulfilled = true;
            $anyFulfilled = false;
            foreach ($items as $item) {
                $done = (string) SalesOrderFulfillmentItem::query()->where('sales_order_item_id', $item->id)->sum('quantity');
                if (bccomp($done, '0', 4) > 0) {
                    $anyFulfilled = true;
                }
                if (bccomp($done, (string) $item->quantity, 4) < 0) {
                    $allFulfilled = false;
                }
            }
            $from = $locked->status;
            $locked->status = $allFulfilled ? 'fulfilled' : ($anyFulfilled ? 'partially_fulfilled' : 'processing');
            $locked->save();
            SalesOrderStatusHistory::query()->create([
                'sales_order_id' => $locked->id, 'from_status' => $from, 'to_status' => $locked->status,
                'reason' => 'Order fulfillment '.$fulfillment->fulfillment_number.' recorded.',
                'metadata' => ['fulfillment_id' => $fulfillment->id, 'fulfillment_number' => $fulfillment->fulfillment_number],
                'changed_by' => $actor->id, 'created_at' => now(),
            ]);

            return $locked->load(['items', 'location', 'statusHistory.actor', 'fulfillments.items']);
        }, 3);
    }

    private function assertFinancialIntegrity(SalesOrder $order): void
    {
        $items = $order->items()->orderBy('id')->get();
        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['pricing' => 'An order without line items cannot be confirmed.']);
        }

        $snapshot = $order->pricing_snapshot ?? [];
        $basis = $snapshot['price_basis'] ?? 'catalogue_and_commercial_rules';
        if ($basis === 'b2b_quote') {
            $grossSubtotal = '0.00';
            $netLineSubtotal = '0.00';
            $lineDiscounts = '0.00';
            foreach ($items as $item) {
                $gross = bcmul((string) $item->quantity, (string) $item->unit_price, 2);
                $lineDiscount = number_format((float) (($item->pricing_snapshot ?? [])['line_discount'] ?? 0), 2, '.', '');
                if (bccomp($lineDiscount, $gross, 2) > 0
                    || bccomp((string) $item->line_total, bcsub($gross, $lineDiscount, 2), 2) !== 0) {
                    throw ValidationException::withMessages(['pricing' => 'An accepted quotation line does not reconcile with its saved price and discount.']);
                }
                $grossSubtotal = bcadd($grossSubtotal, $gross, 2);
                $netLineSubtotal = bcadd($netLineSubtotal, (string) $item->line_total, 2);
                $lineDiscounts = bcadd($lineDiscounts, $lineDiscount, 2);
            }
            $quoteDiscount = number_format((float) ($snapshot['accepted_discount'] ?? 0), 2, '.', '');
            $acceptedLineSubtotal = number_format((float) ($snapshot['accepted_line_subtotal'] ?? 0), 2, '.', '');
            $acceptedTax = number_format((float) ($snapshot['accepted_tax'] ?? 0), 2, '.', '');
            $acceptedTotal = number_format((float) ($snapshot['accepted_total'] ?? 0), 2, '.', '');
            if (bccomp($grossSubtotal, (string) $order->subtotal, 2) !== 0
                || bccomp($netLineSubtotal, $acceptedLineSubtotal, 2) !== 0
                || bccomp($acceptedTax, (string) $order->tax_total, 2) !== 0
                || bccomp($acceptedTotal, (string) $order->grand_total, 2) !== 0
                || bccomp(bcadd($lineDiscounts, $quoteDiscount, 2), (string) $order->discount_total, 2) !== 0) {
                throw ValidationException::withMessages(['pricing' => 'Accepted quotation lines, discounts and order subtotal no longer reconcile.']);
            }
        } else {
            $grossSubtotal = '0.00';
            $lineDiscounts = '0.00';
            foreach ($items as $item) {
                $gross = bcmul((string) $item->quantity, (string) $item->unit_price, 2);
                $lineDiscount = number_format((float) (($item->pricing_snapshot ?? [])['line_discount'] ?? 0), 2, '.', '');
                if (bccomp($lineDiscount, $gross, 2) > 0
                    || bccomp((string) $item->line_total, bcsub($gross, $lineDiscount, 2), 2) !== 0) {
                    throw ValidationException::withMessages(['pricing' => 'An order line does not reconcile with its immutable unit price and line discount.']);
                }
                $grossSubtotal = bcadd($grossSubtotal, $gross, 2);
                $lineDiscounts = bcadd($lineDiscounts, $lineDiscount, 2);
            }
            if (bccomp($grossSubtotal, (string) $order->subtotal, 2) !== 0) {
                throw ValidationException::withMessages(['pricing' => 'Order lines no longer reconcile with the saved subtotal.']);
            }
            $promotionDiscount = number_format((float) ($snapshot['promotion_discount'] ?? 0), 2, '.', '');
            $manualDiscount = number_format((float) ($snapshot['manual_discount'] ?? 0), 2, '.', '');
            if (bccomp(bcadd($promotionDiscount, $manualDiscount, 2), (string) $order->discount_total, 2) !== 0
                || bccomp($lineDiscounts, $promotionDiscount, 2) > 0) {
                throw ValidationException::withMessages(['pricing' => 'Order discounts no longer reconcile with the saved pricing snapshot.']);
            }
        }

        $expectedGrand = bcadd(
            bcsub((string) $order->subtotal, (string) $order->discount_total, 2),
            (string) $order->tax_total,
            2
        );
        if (bccomp($expectedGrand, (string) $order->grand_total, 2) !== 0
            || bccomp((string) $order->discount_total, (string) $order->subtotal, 2) > 0) {
            throw ValidationException::withMessages(['pricing' => 'Order subtotal, discount, tax and grand total do not reconcile.']);
        }
    }

    public function confirm(SalesOrder $order, User $actor): SalesOrder
    {
        return DB::transaction(function () use ($order, $actor): SalesOrder {
            $locked = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->status === 'confirmed') {
                return $locked->load(['items', 'location']);
            }
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Only pending orders can be confirmed.']);
            }

            $this->assertFinancialIntegrity($locked);
            if ($locked->b2b_account_id) {
                $account = B2BAccount::query()->lockForUpdate()->findOrFail($locked->b2b_account_id);
                if ($account->status !== 'active') {
                    throw ValidationException::withMessages(['account' => 'The business account must remain active until order confirmation.']);
                }
                $creditSnapshot = $this->b2bCredit->assess($account, (string) $locked->grand_total, (int) $locked->id);
                $locked->credit_snapshot = $creditSnapshot;
                $locked->payment_due_at = $creditSnapshot['payment_due_at'];
                $locked->save();
            }

            $activeCount = InventoryReservation::query()
                ->where('reference_type', 'sales_order')->where('reference_id', (string) $locked->id)
                ->where('status', 'active')->count();
            if ($activeCount !== $locked->items()->count()) {
                throw ValidationException::withMessages(['status' => 'One or more stock reservations have expired or been released. Recreate the order before confirming.']);
            }

            $this->commercialPricing->consumeOrderFlashSales($locked);
            $locked->status = 'confirmed';
            $locked->confirmed_at = now();
            $locked->save();
            SalesOrderStatusHistory::query()->create([
                'sales_order_id' => $locked->id, 'from_status' => 'pending', 'to_status' => 'confirmed',
                'reason' => 'Order confirmed.', 'changed_by' => $actor->id, 'created_at' => now(),
            ]);

            return $locked->load(['items', 'location', 'statusHistory.actor']);
        }, 3);
    }
}
