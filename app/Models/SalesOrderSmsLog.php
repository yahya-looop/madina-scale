<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderSmsLog extends Model
{
    protected $fillable = ['sales_order_id', 'recipient_phone', 'event', 'message', 'provider', 'status', 'provider_message_id', 'error_message', 'provider_response', 'sent_at'];
    protected function casts(): array { return ['provider_response' => 'array', 'sent_at' => 'datetime']; }
    public function order(): BelongsTo { return $this->belongsTo(SalesOrder::class, 'sales_order_id'); }
}
