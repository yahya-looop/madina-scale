<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesOrderFulfillment extends Model
{
    protected $fillable = ['fulfillment_number', 'sales_order_id', 'status', 'idempotency_key', 'notes', 'created_by', 'fulfilled_at', 'cogs_amount', 'journal_entry_id'];

    protected function casts(): array
    {
        return ['fulfilled_at' => 'datetime', 'cogs_amount' => 'decimal:2'];
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<SalesOrderFulfillmentItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SalesOrderFulfillmentItem::class, 'sales_order_fulfillment_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
