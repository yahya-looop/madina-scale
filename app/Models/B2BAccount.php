<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2BAccount extends Model
{
    protected $table = 'b2b_accounts';

    protected $fillable = [
        'account_code', 'company_name', 'legal_name', 'business_type', 'tier', 'status',
        'customer_id', 'lead_id', 'contact_name', 'email', 'phone', 'tax_id',
        'registration_number', 'payment_terms', 'credit_limit', 'default_discount_percent',
        'billing_address', 'billing_area', 'billing_district', 'billing_division', 'billing_postal_code',
        'shipping_address', 'shipping_area', 'shipping_district', 'shipping_division', 'shipping_postal_code',
        'notes', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return ['credit_limit' => 'decimal:2', 'default_discount_percent' => 'integer'];
    }

    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function lead(): BelongsTo { return $this->belongsTo(Lead::class); }
    public function quotes(): HasMany { return $this->hasMany(B2BQuote::class, 'b2b_account_id'); }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') return $query;
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
        return $query->where(fn (Builder $q) => $q->where('account_code', 'like', "%{$escaped}%")
            ->orWhere('company_name', 'like', "%{$escaped}%")
            ->orWhere('legal_name', 'like', "%{$escaped}%")
            ->orWhere('contact_name', 'like', "%{$escaped}%")
            ->orWhere('email', 'like', "%{$escaped}%")
            ->orWhere('phone', 'like', "%{$escaped}%"));
    }
}
