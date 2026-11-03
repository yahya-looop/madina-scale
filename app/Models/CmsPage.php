<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CmsPage extends Model
{
    protected $fillable = ['title', 'slug', 'excerpt', 'content', 'sections', 'featured_image', 'status', 'published_at', 'created_by', 'updated_by', 'meta_title', 'meta_description', 'meta_keywords', 'canonical_url', 'robots_index', 'robots_follow'];

    protected function casts(): array
    {
        return ['sections' => 'array', 'published_at' => 'datetime', 'robots_index' => 'boolean', 'robots_follow' => 'boolean'];
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(CmsPageRevision::class)->latest('version');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
