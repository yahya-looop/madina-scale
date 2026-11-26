<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable financial change snapshot for a sales order.
 */
class SalesOrderAdjustment extends Model
{
    protected $fillable = [
        'sales_order_id', 'created_by', 'adjustment_number', 'reason',
        'before_snapshot', 'after_snapshot', 'previous_total', 'new_total',
        'financial_delta', 'settlement_status', 'courier_sync_status',
        'financial_transaction_id',
    ];

    protected function casts(): array
    {
        return [
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'previous_total' => 'decimal:2',
            'new_total' => 'decimal:2',
            'financial_delta' => 'decimal:2',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function financialTransaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(SalesOrderAdjustmentSettlement::class, 'sales_order_adjustment_id')->latest('id');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Order adjustment records are immutable.'));
        static::deleting(fn () => throw new \LogicException('Order adjustment records are immutable.'));
    }
}
