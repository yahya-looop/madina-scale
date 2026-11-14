<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_id
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size
 * @property Carbon|null $expires_at
 */
class SupplierDocument extends Model
{
    protected $fillable = [
        'supplier_id', 'category', 'disk', 'path', 'original_name', 'mime_type',
        'size', 'expires_at', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['size' => 'integer', 'expires_at' => 'date'];
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
