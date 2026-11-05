<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductMedia extends Model
{
    /** @use HasFactory<Factory<ProductMedia>> */
    use HasFactory, SoftDeletes;

    protected $table = 'product_media';

    protected $fillable = [
        'product_id',
        'disk',
        'path',
        'collection',
        'original_name',
        'mime_type',
        'size',
        'width',
        'height',
        'alt_text',
        'sort_order',
        'is_primary',
    ];

    protected $casts = [
        'product_id' => 'integer',
        'size' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
        'sort_order' => 'integer',
        'is_primary' => 'boolean',
    ];

    /**
     * Product that owns this media item.
     */
    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Determine whether this media item is the
     * primary media item.
     */
    public function isPrimary(): bool
    {
        return $this->is_primary;
    }

    /**
     * Determine whether this media item is an image.
     */
    public function isImage(): bool
    {
        return is_string($this->mime_type)
            && str_starts_with($this->mime_type, 'image/');
    }
}
