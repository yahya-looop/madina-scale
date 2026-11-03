<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsBanner extends Model
{
    protected $fillable = ['title', 'image_url', 'alt_text', 'link_url', 'placement', 'status', 'starts_at', 'ends_at', 'sort_order', 'created_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}
