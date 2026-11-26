<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class B2BRfq extends Model
{
    protected $table = 'b2b_rfqs';
    protected $fillable = ['rfq_number', 'b2b_account_id', 'company_name', 'contact_name', 'email', 'phone', 'status', 'required_by', 'delivery_location', 'requirements', 'admin_notes', 'reviewed_by', 'reviewed_at'];
    protected function casts(): array { return ['required_by' => 'date', 'reviewed_at' => 'datetime']; }
    public function account(): BelongsTo { return $this->belongsTo(B2BAccount::class, 'b2b_account_id'); }
    public function items(): HasMany { return $this->hasMany(B2BRfqItem::class, 'b2b_rfq_id'); }
    public function quotes(): HasMany { return $this->hasMany(B2BQuote::class, 'b2b_rfq_id'); }
}
