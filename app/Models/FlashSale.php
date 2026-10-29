<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class FlashSale extends Model
{
    protected $fillable = [
        'title', 'slug', 'tagline', 'description', 'status', 'banner_image',
        'accent_color', 'starts_at', 'ends_at', 'show_on_storefront', 'priority',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'show_on_storefront' => 'boolean',
            'priority' => 'integer',
        ];
    }

    public function items(): HasMany { return $this->hasMany(FlashSaleItem::class)->orderBy('sort_order'); }

    public function isLive(): bool
    {
        return $this->status === 'active'
            && $this->show_on_storefront
            && $this->starts_at->lte(now())
            && $this->ends_at->gte(now());
    }

    protected static function booted(): void
    {
        static::creating(function (FlashSale $sale): void {
            if (blank($sale->slug)) {
                $sale->slug = Str::slug($sale->title).'-'.Str::lower(Str::random(5));
            }
        });
    }
}
