<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $sales_order_id
 * @property int $location_id
 * @property int|null $customer_id
 * @property int|null $cash_account_id
 * @property int|null $journal_entry_id
 * @property numeric-string $refund_amount
 * @property Carbon $return_date
 */
class SalesReturn extends Model
{
    protected $fillable = ['return_number', 'sales_order_id', 'location_id', 'customer_id', 'status', 'refund_method', 'cash_account_id', 'refund_amount', 'return_date', 'reason', 'idempotency_key', 'journal_entry_id', 'created_by'];

    protected function casts(): array
    {
        return ['refund_amount' => 'decimal:2', 'return_date' => 'date'];
    }

    /** @return BelongsTo<SalesOrder, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    /** @return BelongsTo<Location, $this> */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /** @return BelongsTo<Customer, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** @return BelongsTo<FinancialAccount, $this> */
    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(FinancialAccount::class, 'cash_account_id');
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** @return HasMany<SalesReturnItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(SalesReturnItem::class);
    }
}
