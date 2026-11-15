<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CustomerOtpService
{
    public function send(string $phone, string $purpose, ?string $ipAddress = null): void
    {
        $recent = DB::table('customer_login_otps')
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->where('created_at', '>=', now()->subSeconds((int) config('customer_auth.otp_resend_seconds', 60)))
            ->exists();

        if ($recent) {
            throw ValidationException::withMessages([
                'phone' => 'Please wait before requesting another verification code.',
            ]);
        }

        $code = (string) random_int(100000, 999999);
        DB::table('customer_login_otps')->where('phone', $phone)->where('purpose', $purpose)
            ->whereNull('consumed_at')->update(['consumed_at' => now(), 'updated_at' => now()]);

        DB::table('customer_login_otps')->insert([
            'phone' => $phone,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'attempts' => 0,
            'expires_at' => now()->addMinutes((int) config('customer_auth.otp_ttl_minutes', 5)),
            'ip_address' => $ipAddress,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sms = config('order_integrations.sms');
        if (! empty($sms['enabled']) && ! empty($sms['endpoint']) && ! empty($sms['api_key'])) {
            $message = 'Madina Scale verification code: '.$code.'. It expires in '.(int) config('customer_auth.otp_ttl_minutes', 5).' minutes. Do not share this code.';
            $request = Http::timeout((int) ($sms['timeout'] ?? 10))->acceptJson();
            $payload = [
                ($sms['to_field'] ?? 'to') => $this->formatPhone($phone),
                ($sms['message_field'] ?? 'message') => $message,
            ];

            if (($sms['auth_mode'] ?? 'header') === 'body') {
                $payload[$sms['api_key_field'] ?? 'api_key'] = $sms['api_key'];
            } else {
                $request = $request->withHeaders([
                    (string) ($sms['api_key_header'] ?? 'Authorization') =>
                    (string) ($sms['api_key_prefix'] ?? 'Bearer ').$sms['api_key'],
                ]);
            }
            if (! empty($sms['sender_id'])) {
                $payload[$sms['sender_field'] ?? 'sender_id'] = $sms['sender_id'];
            }

            $response = $request->post((string) $sms['endpoint'], $payload);
            if (! $response->successful()) {
                throw new RuntimeException('Unable to deliver verification SMS. Please try again later.');
            }

            return;
        }

        if (app()->environment('local', 'testing') && config('customer_auth.log_otp_when_sms_unconfigured', true)) {
            Log::notice('Customer authentication OTP generated (local/testing only).', [
                'phone' => $phone,
                'purpose' => $purpose,
                'code' => $code,
            ]);

            return;
        }

        throw new RuntimeException('SMS verification is temporarily unavailable. Please try again later.');
    }

    public function verify(string $phone, string $purpose, string $code): bool
    {
        $challenge = DB::table('customer_login_otps')
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();

        if (! $challenge || (int) $challenge->attempts >= (int) config('customer_auth.otp_max_attempts', 5)) {
            return false;
        }

        if (! Hash::check($code, $challenge->code_hash)) {
            DB::table('customer_login_otps')->where('id', $challenge->id)->increment('attempts');

            return false;
        }

        DB::table('customer_login_otps')->where('id', $challenge->id)->update([
            'consumed_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }

    private function formatPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';

        return str_starts_with($digits, '880') ? '+'.$digits : '+88'.$digits;
    }
}
