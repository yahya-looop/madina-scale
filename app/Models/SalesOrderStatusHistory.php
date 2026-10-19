<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['sales_order_id', 'from_status', 'to_status', 'reason', 'metadata', 'changed_by', 'created_at'];

    protected function casts(): array { return ['metadata' => 'array', 'created_at' => 'datetime']; }

    public function order(): BelongsTo { return $this->belongsTo(SalesOrder::class, 'sales_order_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'changed_by'); }
}
