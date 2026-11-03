<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Lead extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'lead_code', 'name', 'phone', 'alternate_phone', 'email', 'address_line',
        'area', 'district', 'division', 'source', 'source_detail', 'customer_type',
        'interested_product', 'status', 'last_contact_outcome', 'interest_level',
        'notes', 'next_follow_up_at', 'assigned_to', 'customer_id', 'converted_order_id',
        'created_by', 'updated_by', 'location_id', 'owner_id',
    ];

    protected function casts(): array
    {
        return ['next_follow_up_at' => 'datetime'];
    }

    /** @return HasMany<LeadAssignmentHistory, $this> */
    public function assignmentHistory(): HasMany
    {
        return $this->hasMany(LeadAssignmentHistory::class)->latest();
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return HasMany<LeadActivity, $this> */
    public function activities(): HasMany
    {
        return $this->hasMany(LeadActivity::class)->latest('occurred_at');
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'converted_order_id');
    }

    /**
     * @param  Builder<Lead>  $query
     * @return Builder<Lead>
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);

        return $query->where(function (Builder $q) use ($escaped): void {
            $q->where('lead_code', 'like', "%{$escaped}%")
                ->orWhere('name', 'like', "%{$escaped}%")
                ->orWhere('phone', 'like', "%{$escaped}%")
                ->orWhere('area', 'like', "%{$escaped}%")
                ->orWhere('district', 'like', "%{$escaped}%")
                ->orWhere('interested_product', 'like', "%{$escaped}%");
        });
    }
}
