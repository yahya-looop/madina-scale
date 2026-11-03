<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingCampaignEvent extends Model
{
    protected $fillable = ['campaign_id', 'event_type', 'source', 'visitor_key', 'customer_id', 'order_id', 'value', 'metadata', 'occurred_at'];

    protected function casts(): array
    {
        return ['value' => 'decimal:2', 'metadata' => 'array', 'occurred_at' => 'datetime'];
    }
}
