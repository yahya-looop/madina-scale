<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property string|null $sku
 * @property string $product_type
 * @property string $status
 * @property string $visibility
 */
class Product extends Model
{
    /** @use HasFactory<Factory<Product>> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'category_id',
        'brand_id',
        'unit_id',
        'name',
        'slug',
        'sku',
        'model_number',
        'barcode',
        'product_type',
        'short_description',
        'description',
        'status',
        'visibility',
        'sort_order',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    /** @return BelongsTo<ProductCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class);
    }

    /** @return BelongsTo<Brand, $this> */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /** @return BelongsTo<Unit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * All variants belonging to this product.
     *
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Active variants belonging to this product.
     *
     * @return HasMany<ProductVariant, $this>
     */
    public function activeVariants(): HasMany
    {
        return $this->variants()->where('status', 'active');
    }

    /**
     * Default variant of this product.
     *
     * The database and application logic must ensure
     * that only one default variant exists per product.
     *
     * @return HasOne<ProductVariant, $this>
     */
    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ProductVariant::class)
            ->where('is_default', true);
    }

    /**
     * All media attached to this product.
     *
     * Ordered by sort_order first, then id to keep
     * the result deterministic.
     *
     * @return HasMany<ProductMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(ProductMedia::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    /**
     * Primary image/media of the product.
     *
     * @return HasOne<ProductMedia, $this>
     */
    public function primaryMedia(): HasOne
    {
        return $this->hasOne(ProductMedia::class)
            ->where('collection', 'images')
            ->where('is_primary', true);
    }

    /*
    |--------------------------------------------------------------------------
    | Product Type Helpers
    |--------------------------------------------------------------------------
    */

    public function isSimple(): bool
    {
        return $this->product_type === 'simple';
    }

    public function isVariable(): bool
    {
        return $this->product_type === 'variable';
    }

    /*
    |--------------------------------------------------------------------------
    | Product Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isDraft(): bool
    {
        return $this->status === 'draft';
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

    /*
    |--------------------------------------------------------------------------
    | Product Visibility Helpers
    |--------------------------------------------------------------------------
    */

    public function isVisible(): bool
    {
        return $this->visibility === 'visible';
    }

    public function isHidden(): bool
    {
        return $this->visibility === 'hidden';
    }
}
