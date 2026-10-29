<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2BQuote extends Model
{
    protected $table = 'b2b_quotes';
    protected $fillable = ['quote_number', 'b2b_account_id', 'status', 'currency', 'valid_until', 'subtotal', 'discount_amount', 'tax_amount', 'total', 'terms', 'notes', 'created_by'];
    protected function casts(): array { return ['valid_until' => 'date', 'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_amount' => 'decimal:2', 'total' => 'decimal:2']; }
    public function account(): BelongsTo { return $this->belongsTo(B2BAccount::class, 'b2b_account_id'); }
    public function items(): HasMany { return $this->hasMany(B2BQuoteItem::class, 'b2b_quote_id'); }
}
