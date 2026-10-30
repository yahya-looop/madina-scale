<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesReturn extends Model
{
    protected $fillable = ['return_number', 'sales_order_id', 'location_id', 'customer_id', 'status', 'refund_method', 'cash_account_id', 'refund_amount', 'return_date', 'reason', 'idempotency_key', 'journal_entry_id', 'created_by'];

    protected function casts(): array { return ['refund_amount' => 'decimal:2', 'return_date' => 'date']; }
    public function order(): BelongsTo { return $this->belongsTo(SalesOrder::class, 'sales_order_id'); }
    public function location(): BelongsTo { return $this->belongsTo(Location::class); }
    public function customer(): BelongsTo { return $this->belongsTo(Customer::class); }
    public function cashAccount(): BelongsTo { return $this->belongsTo(FinancialAccount::class, 'cash_account_id'); }
    public function journalEntry(): BelongsTo { return $this->belongsTo(JournalEntry::class); }
    public function items(): HasMany { return $this->hasMany(SalesReturnItem::class); }
}
