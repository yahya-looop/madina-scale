<?php

use App\Models\Customer;
use Illuminate\Support\Facades\Http;

it('keeps staff login separate and retires the Laravel customer login page', function (): void {
    $this->get('/backend/login')->assertOk();
    $this->get('/login')->assertNotFound();
    $this->get('/account')->assertNotFound();
});

it('allows a customer to sign in with a password without authenticating a staff user', function (): void {
    $customer = Customer::query()->create([
        'customer_code' => 'CUS-TEST-001',
        'name' => 'Test Customer',
        'phone' => '01712345678',
        'email' => 'customer@example.test',
        'password' => bcrypt('customer-password'),
        'status' => 'active',
    ]);

    $this->postJson('/api/v1/storefront/auth/login/password', [
        'identity' => '01712345678',
        'password' => 'customer-password',
    ])->assertOk()
        ->assertJsonPath('data.customer.phone', '01712345678');

    $this->assertAuthenticatedAs($customer, 'customer');
    $this->assertGuest('web');
});

it('sends and verifies a customer login OTP using the configured SMS gateway', function (): void {
    config([
        'order_integrations.sms.enabled' => true,
        'order_integrations.sms.endpoint' => 'https://sms.example.test/send',
        'order_integrations.sms.api_key' => 'test-key',
        'order_integrations.sms.auth_mode' => 'header',
        'customer_auth.otp_resend_seconds' => 0,
    ]);

    $customer = Customer::query()->create([
        'customer_code' => 'CUS-TEST-002',
        'name' => 'OTP Customer',
        'phone' => '01712345679',
        'status' => 'active',
    ]);

    $sentCode = null;
    Http::fake(function ($request) use (&$sentCode) {
        preg_match('/(\d{6})/', (string) data_get($request->data(), 'message'), $matches);
        $sentCode = $matches[1] ?? null;

        return Http::response(['message_id' => 'test-message'], 200);
    });

    $this->postJson('/api/v1/storefront/auth/login/otp', ['phone' => $customer->phone])
        ->assertOk()
        ->assertSessionHas('customer_otp_phone', $customer->phone);

    expect($sentCode)->not->toBeNull();

    $this->postJson('/api/v1/storefront/auth/login/otp/verify', [
        'phone' => $customer->phone,
        'code' => $sentCode,
    ])->assertOk()
        ->assertJsonPath('data.customer.phone', $customer->phone);

    $this->assertAuthenticatedAs($customer, 'customer');
    expect($customer->fresh()->phone_verified_at)->not->toBeNull();
});

it('does not expose customer account data to guests', function (): void {
    $this->getJson('/api/v1/storefront/account')->assertUnauthorized();
    $this->getJson('/api/v1/storefront/auth/me')->assertUnauthorized();
});

it('allows a new customer to register only after phone OTP verification', function (): void {
    config([
        'order_integrations.sms.enabled' => true,
        'order_integrations.sms.endpoint' => 'https://sms.example.test/send',
        'order_integrations.sms.api_key' => 'test-key',
        'order_integrations.sms.auth_mode' => 'header',
        'customer_auth.otp_resend_seconds' => 0,
    ]);

    $sentCode = null;
    Http::fake(function ($request) use (&$sentCode) {
        preg_match('/(\d{6})/', (string) data_get($request->data(), 'message'), $matches);
        $sentCode = $matches[1] ?? null;

        return Http::response(['message_id' => 'test-message'], 200);
    });

    $this->postJson('/api/v1/storefront/auth/register/otp', ['phone' => '01712345680'])
        ->assertOk()
        ->assertSessionHas('customer_registration_phone', '01712345680');
    expect($sentCode)->not->toBeNull();

    $this->postJson('/api/v1/storefront/auth/register', [
        'name' => 'New Customer',
        'phone' => '01712345680',
        'email' => 'new@example.test',
        'password' => 'customer-password',
        'password_confirmation' => 'customer-password',
        'code' => $sentCode,
    ])->assertCreated()
        ->assertJsonPath('data.customer.phone', '01712345680');

    $this->assertAuthenticated('customer');
    expect(Customer::query()->where('phone', '01712345680')->exists())->toBeTrue();
});
