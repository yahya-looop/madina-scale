<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_invoice_id
 * @property int $supplier_id
 * @property int $location_id
 * @property Carbon $return_date
 * @property numeric-string $total_amount
 * @property-read Collection<int, SupplierReturnItem> $items
 */
class SupplierReturn extends Model
{
    protected $fillable = [
        'return_number', 'supplier_invoice_id', 'supplier_id', 'location_id',
        'return_date', 'total_amount', 'status', 'idempotency_key',
        'payload_hash', 'reason', 'journal_entry_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['return_date' => 'date', 'total_amount' => 'decimal:2'];
    }

    /** @return HasMany<SupplierReturnItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierReturnItem::class);
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
