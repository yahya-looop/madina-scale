<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $location_id
 * @property string $status
 * @property string|null $purpose
 * @property Carbon|null $required_by
 * @property int $requested_by
 * @property int|null $converted_purchase_order_id
 * @property-read Collection<int, PurchaseRequisitionItem> $items
 * @property-read Location|null $location
 */
class PurchaseRequisition extends Model
{
    protected $fillable = [
        'requisition_number', 'location_id', 'status', 'purpose', 'required_by',
        'requested_by', 'approved_by', 'approved_at', 'converted_purchase_order_id',
    ];

    protected function casts(): array
    {
        return ['required_by' => 'date', 'approved_at' => 'datetime'];
    }

    /** @return HasMany<PurchaseRequisitionItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequisitionItem::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
