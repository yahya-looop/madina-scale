<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderRiskAssessment extends Model
{
    protected $fillable = ['sales_order_id', 'score', 'level', 'decision', 'signals', 'reviewed_by', 'review_note', 'reviewed_at'];

    protected function casts(): array
    {
        return ['signals' => 'array', 'reviewed_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
