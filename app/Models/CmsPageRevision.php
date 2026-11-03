<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CmsPageRevision extends Model
{
    protected $fillable = ['cms_page_id', 'version', 'snapshot', 'created_by', 'change_note'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }
}
