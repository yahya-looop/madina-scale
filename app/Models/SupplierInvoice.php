<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_id
 * @property int $purchase_order_id
 * @property Carbon $invoice_date
 * @property Carbon|null $due_date
 * @property numeric-string $subtotal
 * @property numeric-string $tax_total
 * @property numeric-string $discount_total
 * @property numeric-string $grand_total
 * @property numeric-string $paid_total
 * @property string $status
 * @property int|null $journal_entry_id
 * @property-read Supplier|null $supplier
 * @property-read PurchaseOrder|null $purchaseOrder
 * @property-read Collection<int, SupplierInvoiceItem> $items
 */
class SupplierInvoice extends Model
{
    protected $fillable = [
        'supplier_id', 'purchase_order_id', 'invoice_number', 'invoice_date',
        'due_date', 'subtotal', 'tax_total', 'discount_total', 'grand_total',
        'paid_total', 'status', 'journal_entry_id', 'created_by', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'posted_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'paid_total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<PurchaseOrder, $this> */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /** @return HasMany<SupplierPayment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(SupplierPayment::class);
    }

    /** @return HasMany<SupplierInvoiceItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SupplierInvoiceItem::class);
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
