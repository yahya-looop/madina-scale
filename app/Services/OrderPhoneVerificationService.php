<?php

namespace App\Services;

use App\Models\SalesOrder;
use App\Models\SalesOrderPhoneVerification;
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
        $recent = SalesOrderPhoneVerification::query()->where('phone', $order->customer_phone)->where('created_at', '>=', now()->subMinutes(10))->count();
        if ($recent >= 3) {
            throw ValidationException::withMessages(['phone' => 'Too many verification codes requested. Try again later.']);
        }
        $code = (string) random_int(100000, 999999);
        $challenge = SalesOrderPhoneVerification::query()->create(['sales_order_id' => $order->id, 'phone' => $order->customer_phone, 'code_hash' => Hash::make($code), 'expires_at' => now()->addMinutes((int) config('order_integrations.verification.ttl_minutes', 5))]);
        $this->sms->send($order, 'verification', 'Madina Scale অর্ডার যাচাই কোড: '.$code.'। কোডটি কাউকে জানাবেন না।', null, true);
    }

    public function verify(SalesOrder $order, string $code): bool
    {
        $challenge = SalesOrderPhoneVerification::query()->where('sales_order_id', $order->id)->whereNull('verified_at')->whereNull('consumed_at')->latest('id')->first();
        if (! $challenge || $challenge->expires_at->isPast() || $challenge->attempts >= (int) config('order_integrations.verification.max_attempts', 5)) {
            throw ValidationException::withMessages(['code' => 'Verification code expired or locked. Request a new code.']);
        }
        if (! Hash::check($code, $challenge->code_hash)) {
            $challenge->increment('attempts');
            throw ValidationException::withMessages(['code' => 'The verification code is incorrect.']);
        }
        $challenge->update(['verified_at' => now(), 'consumed_at' => now()]);

        return true;
    }
}
