<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrder extends Model
{
    protected $fillable = [
        'order_number', 'status', 'channel', 'location_id', 'customer_id', 'b2b_account_id', 'b2b_quote_id', 'lead_id', 'source_type', 'source_id', 'promotion_id', 'promotion_code',
        'customer_name', 'customer_email', 'customer_phone',
        'currency', 'subtotal', 'discount_total', 'tax_total', 'grand_total', 'pricing_snapshot',
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
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function b2bAccount(): BelongsTo { return $this->belongsTo(B2BAccount::class, 'b2b_account_id'); }

    public function b2bQuote(): BelongsTo { return $this->belongsTo(B2BQuote::class, 'b2b_quote_id'); }

    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderItem::class, 'sales_order_id');
    }

    public function promotionRedemptions(): HasMany
    {
        return $this->hasMany(PricingPromotionRedemption::class, 'sales_order_id');
    }

    public function flashSaleAllocations(): HasMany
    {
        return $this->hasMany(SalesOrderFlashSaleAllocation::class, 'sales_order_id');
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(SalesOrderStatusHistory::class, 'sales_order_id')->latest('id');
    }

    public function fulfillments(): HasMany
    {
        return $this->hasMany(SalesOrderFulfillment::class, 'sales_order_id')->latest('id');
    }

    public function shipments(): HasMany
    {
        return $this->hasMany(SalesOrderShipment::class, 'sales_order_id')->latest('id');
    }

    public function riskAssessments(): HasMany
    {
        return $this->hasMany(SalesOrderRiskAssessment::class, 'sales_order_id')->latest('id');
    }

    public function smsLogs(): HasMany
    {
        return $this->hasMany(SalesOrderSmsLog::class, 'sales_order_id')->latest('id');
    }
}
