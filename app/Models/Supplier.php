<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $supplier_code
 * @property string $name
 * @property string|null $email
 * @property string|null $phone
 * @property string $status
 * @property int $credit_days
 * @property-read Collection<int, PurchaseOrder> $purchaseOrders
 * @property-read Collection<int, SupplierInvoice> $invoices
 */
class Supplier extends Model
{
    protected $fillable = [
        'supplier_code', 'name', 'legal_name', 'email', 'phone', 'tax_number',
        'contact_person', 'address', 'city', 'country', 'payment_terms',
        'credit_days', 'status', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return ['credit_days' => 'integer'];
    }

    /** @return HasMany<PurchaseOrder, $this> */
    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    /** @return HasMany<SupplierInvoice, $this> */
    public function invoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    /** @return HasMany<SupplierContact, $this> */
    public function contacts(): HasMany
    {
        return $this->hasMany(SupplierContact::class)->orderByDesc('is_primary')->orderBy('name');
    }

    /** @return HasMany<SupplierDocument, $this> */
    public function documents(): HasMany
    {
        return $this->hasMany(SupplierDocument::class)->latest();
    }
}
