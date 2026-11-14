<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $inventory_stock_id
 * @property int|null $stock_movement_id
 * @property numeric-string $quantity_delta
 * @property numeric-string $unit_cost
 * @property numeric-string $value_delta
 * @property numeric-string $quantity_before
 * @property numeric-string $quantity_after
 * @property numeric-string $value_before
 * @property numeric-string $value_after
 * @property string|null $reference_type
 * @property string|null $reference_id
 * @property array<string, mixed>|null $metadata
 */
class InventoryValuationMovement extends Model
{
    protected $fillable = [
        'inventory_stock_id', 'stock_movement_id', 'movement_type', 'quantity_delta',
        'unit_cost', 'value_delta', 'quantity_before', 'quantity_after', 'value_before',
        'value_after', 'reference_type', 'reference_id', 'idempotency_key', 'created_by', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'inventory_stock_id' => 'integer', 'stock_movement_id' => 'integer', 'created_by' => 'integer',
            'quantity_delta' => 'decimal:4', 'unit_cost' => 'decimal:6', 'value_delta' => 'decimal:2',
            'quantity_before' => 'decimal:4', 'quantity_after' => 'decimal:4',
            'value_before' => 'decimal:2', 'value_after' => 'decimal:2', 'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<InventoryStock, $this> */
    public function stock(): BelongsTo
    {
        return $this->belongsTo(InventoryStock::class, 'inventory_stock_id');
    }

    /** @return BelongsTo<StockMovement, $this> */
    public function stockMovement(): BelongsTo
    {
        return $this->belongsTo(StockMovement::class);
    }
}
