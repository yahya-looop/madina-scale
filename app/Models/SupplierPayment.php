<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_invoice_id
 * @property int $supplier_id
 * @property int $location_id
 * @property Carbon $payment_date
 * @property numeric-string $amount
 */
class SupplierPayment extends Model
{
    protected $fillable = [
        'payment_number', 'supplier_invoice_id', 'supplier_id', 'location_id',
        'payment_date', 'amount', 'payment_method', 'cash_account_id',
        'journal_entry_id', 'reference', 'idempotency_key', 'payload_hash',
        'created_by',
    ];

    protected function casts(): array
    {
        return ['payment_date' => 'date', 'amount' => 'decimal:2'];
    }

    /** @return BelongsTo<SupplierInvoice, $this> */
    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
