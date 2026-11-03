<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $location_id
 * @property int|null $customer_id
 * @property int|null $b2b_account_id
 * @property int|null $b2b_quote_id
 * @property int|null $promotion_id
 * @property int|null $created_by
 * @property numeric-string $subtotal
 * @property numeric-string $discount_total
 * @property numeric-string $tax_total
 * @property numeric-string $grand_total
 * @property array<string, mixed>|null $pricing_snapshot
 * @property array<string, mixed>|null $credit_snapshot
 * @property-read Collection<int, SalesOrderItem> $items
 */
class SalesOrder extends Model
{
    protected $fillable = [
        'order_number', 'status', 'channel', 'location_id', 'customer_id', 'b2b_account_id', 'b2b_quote_id', 'lead_id', 'source_type', 'source_id', 'promotion_id', 'promotion_code',
        'customer_name', 'customer_email', 'customer_phone',
        'currency', 'subtotal', 'discount_total', 'tax_total', 'grand_total', 'pricing_snapshot', 'credit_snapshot', 'payment_due_at',
        'notes', 'created_by', 'confirmed_at', 'cancelled_at', 'cancellation_reason',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'pricing_snapshot' => 'array',
            'credit_snapshot' => 'array',
            'payment_due_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<B2BAccount, $this> */
    public function b2bAccount(): BelongsTo
    {
        return $this->belongsTo(B2BAccount::class, 'b2b_account_id');
    }

    /** @return BelongsTo<B2BQuote, $this> */
    public function b2bQuote(): BelongsTo
    {
        return $this->belongsTo(B2BQuote::class, 'b2b_quote_id');
    }

    /** @return BelongsTo<Lead, $this> */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<SalesOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_id');
    }

    /** @return HasMany<PricingPromotionRedemption, $this> */
    public function promotionRedemptions(): HasMany
    {
        return $this->hasMany(PricingPromotionRedemption::class, 'sales_order_id');
    }

    /** @return HasMany<SalesOrderFlashSaleAllocation, $this> */
    public function flashSaleAllocations(): HasMany
    {
        return $this->hasMany(SalesOrderFlashSaleAllocation::class, 'sales_order_id');
    }

    /** @return HasMany<SalesOrderStatusHistory, $this> */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(SalesOrderStatusHistory::class, 'sales_order_id')->latest('id');
    }

    /** @return HasMany<SalesOrderFulfillment, $this> */
    public function fulfillments(): HasMany
    {
        return $this->hasMany(SalesOrderFulfillment::class, 'sales_order_id')->latest('id');
    }

    /** @return HasMany<SalesOrderShipment, $this> */
    public function shipments(): HasMany
    {
        return $this->hasMany(SalesOrderShipment::class, 'sales_order_id')->latest('id');
    }

    /** @return HasMany<SalesOrderRiskAssessment, $this> */
    public function riskAssessments(): HasMany
    {
        return $this->hasMany(SalesOrderRiskAssessment::class, 'sales_order_id')->latest('id');
    }

    /** @return HasMany<SalesOrderSmsLog, $this> */
    public function smsLogs(): HasMany
    {
        return $this->hasMany(SalesOrderSmsLog::class, 'sales_order_id')->latest('id');
    }
}
