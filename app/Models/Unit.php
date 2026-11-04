<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Unit extends Model
{
    /** @use HasFactory<Factory<Unit>> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'short_name',
        'slug',
        'description',
        'status',
        'sort_order',
    ];

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
}
