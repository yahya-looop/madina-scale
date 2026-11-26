<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderAdjustmentSettlement extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'sales_order_adjustment_id', 'settlement_type', 'amount',
        'financial_transaction_id', 'reference', 'created_by', 'created_at',
    ];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'created_at' => 'datetime'];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(SalesOrderAdjustment::class, 'sales_order_adjustment_id');
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(FinancialTransaction::class, 'financial_transaction_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Adjustment settlements are immutable.'));
        static::deleting(fn () => throw new \LogicException('Adjustment settlements are immutable.'));
    }
}
