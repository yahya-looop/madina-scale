<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $b2b_quote_id
 * @property int|null $product_variant_id
 * @property numeric-string $quantity
 * @property numeric-string $unit_price
 * @property numeric-string $discount_percent
 * @property numeric-string $line_total
 */
class B2BQuoteItem extends Model
{
    protected $table = 'b2b_quote_items';

    protected $fillable = ['b2b_quote_id', 'product_variant_id', 'sku', 'description', 'quantity', 'unit_price', 'discount_percent', 'line_total'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'unit_price' => 'decimal:2', 'discount_percent' => 'decimal:2', 'line_total' => 'decimal:2'];
    }

    /** @return BelongsTo<B2BQuote, $this> */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(B2BQuote::class, 'b2b_quote_id');
    }
}
