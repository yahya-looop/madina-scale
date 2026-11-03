<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Lead;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerIdentityService
{
    public function normalizePhone(?string $phone): ?string
    {
        $digits = preg_replace('/\\D+/', '', (string) $phone);

        return $digits === '' ? null : $digits;
    }

    public function normalizeEmail(?string $email): ?string
    {
        $email = mb_strtolower(trim((string) $email));

        return $email === '' ? null : $email;
    }

    /** Reject normalized identity collisions for manual customer create/update. */
    public function assertAvailable(?string $phoneValue, ?string $emailValue, ?int $ignoreCustomerId = null): void
    {
        $phone = $this->normalizePhone($phoneValue);
        $email = $this->normalizeEmail($emailValue);
        $keys = array_values(array_unique(array_filter([
            $phone ? 'phone:'.hash('sha256', $phone) : null,
            $email ? 'email:'.hash('sha256', $email) : null,
        ])));
        sort($keys, SORT_STRING);
        foreach ($keys as $key) {
            DB::table('customer_identity_locks')->insertOrIgnore(['identity_key' => $key, 'created_at' => now(), 'updated_at' => now()]);
        }
        if ($keys) {
            DB::table('customer_identity_locks')->whereIn('identity_key', $keys)->orderBy('identity_key')->lockForUpdate()->get();
        }

        $duplicate = Customer::query()->when($ignoreCustomerId, fn ($q) => $q->whereKeyNot($ignoreCustomerId))->get()->first(function (Customer $customer) use ($phone, $email): bool {
            return (bool) (($phone && $this->normalizePhone($customer->phone) === $phone) || ($email && $this->normalizeEmail($customer->email) === $email));
        });
        if ($duplicate) {
            throw ValidationException::withMessages(['phone' => 'A customer with this phone number or email already exists.']);
        }
    }

    /** Resolve an existing customer or create one, serializing identical identities. */
    public function resolveLead(Lead $lead): Customer
    {
        $phone = $this->normalizePhone($lead->phone);
        $email = $this->normalizeEmail($lead->email);
        if (! $phone && ! $email) {
            throw ValidationException::withMessages(['customer' => 'A phone number or email address is required to safely identify a customer.']);
        }

        $keys = array_values(array_unique(array_filter([
            $phone ? 'phone:'.hash('sha256', $phone) : null,
            $email ? 'email:'.hash('sha256', $email) : null,
        ])));
        sort($keys, SORT_STRING);

        foreach ($keys as $key) {
            DB::table('customer_identity_locks')->insertOrIgnore([
                'identity_key' => $key,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
        DB::table('customer_identity_locks')->whereIn('identity_key', $keys)->orderBy('identity_key')->lockForUpdate()->get();

        $matches = Customer::query()->get()->filter(function (Customer $customer) use ($phone, $email): bool {
            $phoneMatch = $phone && $this->normalizePhone($customer->phone) === $phone;
            $emailMatch = $email && $this->normalizeEmail($customer->email) === $email;

            return (bool) ($phoneMatch || $emailMatch);
        })->values();

        $ids = $matches->pluck('id')->unique();
        if ($ids->count() > 1) {
            throw ValidationException::withMessages(['customer' => 'The supplied phone and email belong to different existing customers. Resolve the customer records before converting this lead.']);
        }

        $customer = $matches->first();
        if ($customer) {
            if ($customer->status !== 'active') {
                throw ValidationException::withMessages(['customer' => 'The matched customer is inactive. Reactivate or review the customer before conversion.']);
            }

            return $customer;
        }

        do {
            $code = 'CUS-'.now()->format('ym').'-'.Str::upper(Str::random(6));
        } while (Customer::query()->where('customer_code', $code)->exists());

        return Customer::query()->create([
            'customer_code' => $code,
            'name' => $lead->name,
            'phone' => trim((string) $lead->phone) ?: null,
            'email' => $email,
            'address_line' => $lead->address_line,
            'area' => $lead->area,
            'district' => $lead->district,
            'division' => $lead->division,
            'notes' => $lead->notes,
            'status' => 'active',
        ]);
    }
}
