<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\CustomerOtpService;
use App\Services\CustomerIdentityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class CustomerAuthController extends Controller
{
    public function me(): JsonResponse
    {
        if (! Auth::guard('customer')->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return response()->json(['data' => $this->customerData($customer)]);
    }

    public function passwordLogin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'identity' => ['required', 'string', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
            'remember' => ['sometimes', 'boolean'],
        ]);

        $identity = trim($data['identity']);
        $customer = Customer::query()
            ->where(function ($query) use ($identity): void {
                $query->where('phone', $this->normalizePhone($identity))
                    ->orWhereRaw('LOWER(email) = ?', [mb_strtolower($identity)]);
            })
            ->where('status', 'active')
            ->first();

        if (! $customer || ! $customer->password || ! Hash::check($data['password'], $customer->password)) {
            throw ValidationException::withMessages([
                'identity' => 'The phone/email or password is incorrect.',
            ]);
        }

        Auth::guard('customer')->login($customer, (bool) ($data['remember'] ?? false));
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Signed in successfully.',
            'data' => ['customer' => $this->customerData($customer)],
        ]);
    }

    public function sendLoginOtp(Request $request, CustomerOtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
        ]);
        $phone = $this->normalizePhone($data['phone']);
        $customer = Customer::query()->where('phone', $phone)->where('status', 'active')->first();

        // Keep the response identical for known and unknown numbers.
        if ($customer) {
            try {
                $otp->send($phone, 'login', $request->ip());
            } catch (ValidationException $exception) {
                throw $exception;
            } catch (Throwable) {
                throw ValidationException::withMessages([
                    'phone' => 'SMS verification is temporarily unavailable. Please try again later.',
                ]);
            }
        }

        $request->session()->put('customer_otp_phone', $phone);

        return response()->json([
            'message' => 'If an account exists for this number, a verification code has been sent.',
        ]);
    }

    public function verifyLoginOtp(Request $request, CustomerOtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            'code' => ['required', 'digits:6'],
        ]);
        $phone = $this->normalizePhone($data['phone']);
        $customer = Customer::query()->where('phone', $phone)->where('status', 'active')->first();

        if (! $customer || ! $otp->verify($phone, 'login', $data['code'])) {
            throw ValidationException::withMessages([
                'code' => 'The verification code is invalid or expired.',
            ]);
        }

        $customer->forceFill(['phone_verified_at' => $customer->phone_verified_at ?: now()])->save();
        Auth::guard('customer')->login($customer);
        $request->session()->forget('customer_otp_phone');
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Signed in successfully.',
            'data' => ['customer' => $this->customerData($customer)],
        ]);
    }

    public function sendRegistrationOtp(Request $request, CustomerOtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string', 'max:40', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
        ]);
        $phone = $this->normalizePhone($data['phone']);

        if (Customer::query()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'An account already exists for this number. Please sign in instead.',
            ]);
        }

        try {
            $otp->send($phone, 'register', $request->ip());
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'phone' => 'SMS verification is temporarily unavailable. Please try again later.',
            ]);
        }

        $request->session()->put('customer_registration_phone', $phone);

        return response()->json(['message' => 'A verification code has been sent to your phone.']);
    }

    public function register(Request $request, CustomerOtpService $otp): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^(?:\+?88)?01[3-9]\d{8}$/'],
            'email' => ['nullable', 'email:rfc', 'max:190'],
            'password' => ['required', 'string', 'min:8', 'confirmed', 'max:200'],
            'code' => ['required', 'digits:6'],
        ]);

        $phone = $this->normalizePhone($data['phone']);
        if (Customer::query()->where('phone', $phone)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'An account already exists for this number.',
            ]);
        }
        $email = filled($data['email'] ?? null) ? mb_strtolower(trim($data['email'])) : null;
        if ($email && Customer::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages([
                'email' => 'An account already exists for this email address.',
            ]);
        }

        if (! $otp->verify($phone, 'register', $data['code'])) {
            throw ValidationException::withMessages([
                'code' => 'The verification code is invalid or expired.',
            ]);
        }

        $customer = DB::transaction(function () use ($phone, $email, $data): Customer {
            app(CustomerIdentityService::class)->assertAvailable($phone, $email);

            return Customer::query()->create([
                'customer_code' => 'CUS-'.now()->format('ym').'-'.Str::upper(Str::random(6)),
                'name' => trim($data['name']),
                'phone' => $phone,
                'email' => $email,
                'password' => Hash::make($data['password']),
                'phone_verified_at' => now(),
                'status' => 'active',
            ]);
        });

        Auth::guard('customer')->login($customer);
        $request->session()->forget('customer_registration_phone');
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Your account has been created.',
            'data' => ['customer' => $this->customerData($customer)],
        ], 201);
    }

    public function account(): JsonResponse
    {
        if (! Auth::guard('customer')->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        /** @var Customer $customer */
        $customer = Auth::guard('customer')->user();

        return response()->json([
            'data' => [
                'customer' => $this->customerData($customer),
                'orders' => $customer->orders()->latest()->limit(10)->get([
                    'id', 'order_number', 'status', 'grand_total', 'created_at',
                ]),
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('customer')->logout();
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Signed out successfully.']);
    }

    /** @return array<string, mixed> */
    private function customerData(Customer $customer): array
    {
        return [
            'id' => $customer->id,
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'phone_verified_at' => $customer->phone_verified_at?->toISOString(),
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if (str_starts_with($digits, '880') && strlen($digits) === 13) {
            return '0'.substr($digits, 3);
        }

        return $digits;
    }
}
