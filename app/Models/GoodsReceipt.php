<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int $supplier_id
 * @property int $location_id
 * @property Carbon $received_date
 * @property int|null $journal_entry_id
 * @property-read Collection<int, GoodsReceiptItem> $items
 */
class GoodsReceipt extends Model
{
    protected $fillable = [
        'grn_number', 'purchase_order_id', 'supplier_id', 'location_id',
        'received_date', 'status', 'idempotency_key', 'payload_hash',
        'journal_entry_id', 'delivery_note', 'notes', 'received_by',
    ];

    protected function casts(): array
    {
        return ['received_date' => 'date'];
    }

    /** @return HasMany<GoodsReceiptItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
