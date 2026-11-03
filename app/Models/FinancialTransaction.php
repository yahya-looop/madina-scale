<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $type
 * @property Carbon $transaction_date
 * @property numeric-string $amount
 * @property string|null $payment_method
 * @property string|null $reference
 * @property string|null $description
 * @property int $location_id
 * @property int|null $customer_id
 */
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

    /** @return BelongsTo<FinancialAccount, $this> */
    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'cash_account_id');
    }

    /** @return BelongsTo<FinancialAccount, $this> */
    public function categoryAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'category_account_id');
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
