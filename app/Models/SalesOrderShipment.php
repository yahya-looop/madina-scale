<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesOrderShipment extends Model
{
    protected $fillable = ['sales_order_id', 'provider', 'idempotency_key', 'provider_shipment_id', 'tracking_code', 'status', 'recipient_name', 'recipient_phone', 'recipient_address', 'cod_amount', 'provider_response', 'metadata', 'last_error', 'booked_at', 'delivered_at', 'created_by'];
    protected function casts(): array { return ['cod_amount' => 'decimal:2', 'provider_response' => 'array', 'metadata' => 'array', 'booked_at' => 'datetime', 'delivered_at' => 'datetime']; }
    public function order(): BelongsTo { return $this->belongsTo(SalesOrder::class, 'sales_order_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
