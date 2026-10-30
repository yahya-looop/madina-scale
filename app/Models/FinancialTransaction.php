<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FinancialTransaction extends Model
{
    protected $fillable = [
        'transaction_number', 'type', 'transaction_date', 'amount',
        'payment_method', 'cash_account_id', 'category_account_id',
        'customer_id', 'journal_entry_id', 'reference',
        'description', 'idempotency_key', 'payload_hash', 'location_id', 'created_by',
    ];

    protected function casts(): array
    {
        return ['transaction_date' => 'date', 'amount' => 'decimal:2'];
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'cash_account_id');
    }

    public function categoryAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'category_account_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
