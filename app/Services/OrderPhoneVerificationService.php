<?php

namespace App\Services;

use App\Models\SalesOrder;
use App\Models\SalesOrderPhoneVerification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class OrderPhoneVerificationService
{
    public function __construct(private readonly OrderSmsService $sms) {}

    public function send(SalesOrder $order): void
    {
        if (! config('order_integrations.verification.enabled')) {
            throw ValidationException::withMessages(['verification' => 'Phone verification is disabled.']);
        }
        if (empty($order->customer_phone)) {
            throw ValidationException::withMessages(['customer_phone' => 'Customer phone is required.']);
        }

        $phone = preg_replace('/\\D+/', '', (string) $order->customer_phone) ?: '';
        if ($phone === '') {
            throw ValidationException::withMessages(['customer_phone' => 'A valid customer phone is required.']);
        }

        $identityKey = 'otp:phone:'.hash('sha256', $phone);
        $code = (string) random_int(100000, 999999);

        DB::transaction(function () use ($order, $phone, $identityKey, $code): void {
            // Reuse the existing identity-lock table to serialize OTP sends
            // across different orders that share the same phone number.
            DB::table('customer_identity_locks')->insertOrIgnore([
                'identity_key' => $identityKey,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('customer_identity_locks')
                ->where('identity_key', $identityKey)
                ->lockForUpdate()
                ->first();

            $recent = SalesOrderPhoneVerification::query()
                ->whereRaw("regexp_replace(phone, '[^0-9]', '', 'g') = ?", [$phone])
                ->where('created_at', '>=', now()->subMinutes(10))
                ->count();

            if ($recent >= 3) {
                throw ValidationException::withMessages(['phone' => 'Too many verification codes requested. Try again later.']);
            }

            SalesOrderPhoneVerification::query()->create([
                'sales_order_id' => $order->id,
                'phone' => $order->customer_phone,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes((int) config('order_integrations.verification.ttl_minutes', 5)),
            ]);
        }, 3);

        $this->sms->send(
            $order,
            'verification',
            'Madina Scale অর্ডার যাচাই কোড: '.$code.'। কোডটি কাউকে জানাবেন না।',
            null,
            true,
        );
    }

    public function verify(SalesOrder $order, string $code): bool
    {
        $result = DB::transaction(function () use ($order, $code): string {
            $challenge = SalesOrderPhoneVerification::query()
                ->where('sales_order_id', $order->id)
                ->whereNull('verified_at')
                ->whereNull('consumed_at')
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if (! $challenge) {
                return 'expired_or_locked';
            }

            $maxAttempts = max(1, (int) config('order_integrations.verification.max_attempts', 5));
            $now = now();

            // Compare expiry in the database so timestamp timezone/casting
            // differences cannot incorrectly expire a freshly issued code.
            $isExpired = DB::table('sales_order_phone_verifications')
                ->where('id', $challenge->id)
                ->where('expires_at', '<=', $now)
                ->exists();

            if ($isExpired || (int) $challenge->attempts >= $maxAttempts) {
                return 'expired_or_locked';
            }

            if (! Hash::check($code, $challenge->code_hash)) {
                // Use a direct atomic SQL increment while the challenge row
                // is locked; this guarantees the failed attempt is persisted.
                DB::table('sales_order_phone_verifications')
                    ->where('id', $challenge->id)
                    ->increment('attempts');

                return 'incorrect';
            }

            DB::table('sales_order_phone_verifications')
                ->where('id', $challenge->id)
                ->whereNull('verified_at')
                ->whereNull('consumed_at')
                ->update([
                    'verified_at' => $now,
                    'consumed_at' => $now,
                    'updated_at' => $now,
                ]);

            return 'verified';
        }, 3);

        if ($result === 'incorrect') {
            throw ValidationException::withMessages(['code' => 'The verification code is incorrect.']);
        }

        if ($result !== 'verified') {
            throw ValidationException::withMessages(['code' => 'Verification code expired or locked. Request a new code.']);
        }

        return true;
    }
}
