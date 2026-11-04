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
 * @property int $location_id
 * @property int|null $purchase_requisition_id
 * @property string $status
 * @property Carbon $order_date
 * @property Carbon|null $expected_at
 * @property numeric-string $subtotal
 * @property numeric-string $discount_total
 * @property numeric-string $tax_total
 * @property numeric-string $grand_total
 * @property-read Supplier|null $supplier
 * @property-read Location|null $location
 * @property-read Collection<int, PurchaseOrderItem> $items
 */
class PurchaseOrder extends Model
{
    protected $fillable = [
        'po_number', 'supplier_id', 'location_id', 'purchase_requisition_id',
        'status', 'order_date', 'expected_at', 'currency', 'subtotal',
        'discount_total', 'tax_total', 'grand_total', 'notes', 'created_by',
        'approved_by', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'order_date' => 'date',
            'expected_at' => 'date',
            'approved_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return HasMany<PurchaseOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    /** @return HasMany<GoodsReceipt, $this> */
    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    /** @return HasMany<SupplierInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }
}
