<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $expires_at
 * @property Carbon|null $verified_at
 * @property Carbon|null $consumed_at
 */
class SalesOrderPhoneVerification extends Model
{
    protected $fillable = ['sales_order_id', 'phone', 'code_hash', 'attempts', 'expires_at', 'verified_at', 'consumed_at'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime', 'verified_at' => 'datetime', 'consumed_at' => 'datetime'];
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }
}
