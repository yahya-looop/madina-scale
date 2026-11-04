<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $published_at
 * @property Carbon|null $updated_at
 */
class CmsPage extends Model
{
    protected $fillable = ['title', 'slug', 'excerpt', 'content', 'sections', 'featured_image', 'status', 'published_at', 'created_by', 'updated_by', 'meta_title', 'meta_description', 'meta_keywords', 'canonical_url', 'robots_index', 'robots_follow'];

    protected function casts(): array
    {
        return ['sections' => 'array', 'published_at' => 'datetime', 'robots_index' => 'boolean', 'robots_follow' => 'boolean'];
    }

    /** @return HasMany<CmsPageRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(CmsPageRevision::class)->latest('version');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
