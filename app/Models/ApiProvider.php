<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiProvider extends Model
{
    protected $fillable = [
        'name', 'slug', 'category', 'base_url', 'auth_type',
        'credentials', 'config', 'environment', 'is_active', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'config' => 'encrypted:array',
            'is_active' => 'boolean',
        ];
    }
}
