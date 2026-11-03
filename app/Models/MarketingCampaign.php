<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingCampaign extends Model
{
    protected $fillable = ['name', 'channel', 'objective', 'description', 'status', 'budget', 'starts_at', 'ends_at', 'audience_rules', 'settings', 'created_by'];

    protected function casts(): array
    {
        return ['budget' => 'decimal:2', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'audience_rules' => 'array', 'settings' => 'array'];
    }

    public function events(): HasMany
    {
        return $this->hasMany(MarketingCampaignEvent::class, 'campaign_id');
    }
}
