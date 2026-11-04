<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AttributeValue extends Model
{
    /** @use HasFactory<Factory<AttributeValue>> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'attribute_id',
        'name',
        'slug',
        'value',
        'color_code',
        'status',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'attribute_id' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<Attribute, $this> */
    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }

    /**
     * Variant mappings referencing this value.
     */
    /** @return HasMany<ProductVariantAttributeValue, $this> */
    public function variantAttributeValues(): HasMany
    {
        return $this->hasMany(
            ProductVariantAttributeValue::class
        );
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isInactive(): bool
    {
        return $this->status === 'inactive';
    }

    public function isArchived(): bool
    {
        return $this->status === 'archived';
    }

    public function hasColor(): bool
    {
        return $this->color_code !== null
            && $this->color_code !== '';
    }
}
