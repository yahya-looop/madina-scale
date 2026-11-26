<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $b2b_account_id
 * @property string $status
 * @property string $currency
 * @property string|null $valid_until
 * @property numeric-string $subtotal
 * @property numeric-string $discount_amount
 * @property numeric-string $tax_amount
 * @property numeric-string $total
 * @property int|null $converted_order_id
 */
class B2BQuote extends Model
{
    protected $table = 'b2b_quotes';

    protected $fillable = ['quote_number', 'b2b_account_id', 'b2b_rfq_id', 'revision_of_id', 'revision_number', 'status', 'currency', 'valid_until', 'subtotal', 'discount_amount', 'tax_amount', 'total', 'terms', 'notes', 'created_by', 'converted_order_id', 'accepted_at', 'converted_at'];

    protected function casts(): array
    {
        return ['revision_number' => 'integer', 'valid_until' => 'date', 'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total' => 'decimal:2'];
    }

    /** @return BelongsTo<B2BAccount, $this> */
    public function rfq(): BelongsTo
    {
        return $this->belongsTo(B2BRfq::class, 'b2b_rfq_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(B2BAccount::class, 'b2b_account_id');
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'converted_order_id');
    }

    public function revisionOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revision_of_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(self::class, 'revision_of_id')->orderBy('revision_number');
    }

    /** @return HasMany<B2BQuoteItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(B2BQuoteItem::class, 'b2b_quote_id');
    }
}
