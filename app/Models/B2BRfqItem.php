<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class B2BRfqItem extends Model
{
    protected $table = 'b2b_rfq_items';
    protected $fillable = ['b2b_rfq_id', 'product_variant_id', 'description', 'sku', 'quantity', 'unit', 'specifications'];
    protected function casts(): array { return ['quantity' => 'decimal:3']; }
    public function rfq(): BelongsTo { return $this->belongsTo(B2BRfq::class, 'b2b_rfq_id'); }
    public function variant(): BelongsTo { return $this->belongsTo(ProductVariant::class, 'product_variant_id'); }
}
