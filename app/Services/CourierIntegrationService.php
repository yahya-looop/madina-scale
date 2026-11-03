<?php

namespace App\Services;

use App\Models\SalesOrder;
use App\Models\SalesOrderShipment;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

class CourierIntegrationService
{
    /**
     * @param array{
     *     idempotency_key?: string|null,
     *     recipient_name?: string|null,
     *     recipient_phone?: string|null,
     *     recipient_address: string,
     *     cod_amount?: numeric-string|int|float
     * } $data
     */
    public function book(SalesOrder $order, string $provider, array $data, int $actorId): SalesOrderShipment
    {
        $existing = SalesOrderShipment::query()->where('provider', $provider)->where('idempotency_key', $data['idempotency_key'] ?? null)->whereNotNull('idempotency_key')->first();
        if ($existing) {
            if ((int) $existing->sales_order_id !== (int) $order->id) {
                throw ValidationException::withMessages(['idempotency_key' => 'This idempotency key has already been used for another order.']);
            }

            return $existing;
        }
        $config = config("order_integrations.couriers.{$provider}");
        if (! is_array($config) || empty($config['enabled'])) {
            throw ValidationException::withMessages(['provider' => 'This courier is not enabled in configuration.']);
        }
        if (empty($order->customer_phone)) {
            throw ValidationException::withMessages(['customer_phone' => 'A customer phone is required before booking a courier.']);
        }
        $shipment = SalesOrderShipment::query()->create([
            'sales_order_id' => $order->id, 'provider' => $provider, 'idempotency_key' => $data['idempotency_key'] ?? null, 'status' => 'booking',
            'recipient_name' => $data['recipient_name'] ?? $order->customer_name,
            'recipient_phone' => $data['recipient_phone'] ?? $order->customer_phone,
            'recipient_address' => $data['recipient_address'],
            'cod_amount' => $data['cod_amount'] ?? $order->grand_total,
            'metadata' => ['requested_by' => $actorId], 'created_by' => $actorId,
        ]);
        try {
            $response = match ($provider) {
                'steadfast' => $this->bookSteadfast($config, $order, $shipment),
                'redx' => $this->bookRedx($config, $order, $shipment),
                'pathao' => $this->bookPathao($config, $order, $shipment),
                'custom', 'carrybee' => $this->bookCustom($config, $order, $shipment),
                default => throw new \RuntimeException('Unsupported courier provider.'),
            };
            $tracking = data_get($response, 'consignment.tracking_code') ?? data_get($response, 'tracking_code') ?? data_get($response, 'data.tracking_id') ?? data_get($response, 'tracking_id');
            $providerId = data_get($response, 'consignment.consignment_id') ?? data_get($response, 'data.consignment_id') ?? data_get($response, 'data.id') ?? data_get($response, 'id');
            $shipment->update(['status' => 'booked', 'tracking_code' => $tracking ? (string) $tracking : null, 'provider_shipment_id' => $providerId ? (string) $providerId : null, 'provider_response' => $response, 'booked_at' => now(), 'last_error' => null]);
        } catch (Throwable $exception) {
            $shipment->update(['status' => 'failed', 'last_error' => mb_substr($exception->getMessage(), 0, 2000)]);
            throw ValidationException::withMessages(['courier' => 'Courier booking failed. Review the shipment record and provider configuration before retrying.']);
        }

        return $shipment->refresh();
    }

    /** Retry a shipment that the controller has atomically claimed by changing failed -> booking. */
    public function retryFailed(SalesOrderShipment $shipment, int $actorId, string $reason): SalesOrderShipment
    {
        $shipment = SalesOrderShipment::query()->with('order')->findOrFail($shipment->id);
        if ($shipment->status !== 'booking') {
            throw ValidationException::withMessages(['shipment' => 'The shipment retry was not claimed for processing. Reconcile its current state before retrying.']);
        }

        /** @var array<string, mixed> $metadata */
        $metadata = is_array($shipment->metadata) ? $shipment->metadata : [];
        $storedAttempts = $metadata['retry_attempts'] ?? [];
        if (! is_array($storedAttempts)) {
            $storedAttempts = [];
        }

        /** @var list<array<string, mixed>> $attempts */
        $attempts = [];
        foreach ($storedAttempts as $attempt) {
            if (is_array($attempt)) {
                $attempts[] = $attempt;
            }
        }

        $attemptIndex = count($attempts);
        $attempts[] = [
            'requested_by' => $actorId,
            'reason' => $reason,
            'started_at' => now()->toISOString(),
            'outcome' => 'in_progress',
        ];
        $metadata['retry_attempts'] = $attempts;
        $shipment->update(['metadata' => $metadata, 'last_error' => null]);

        try {
            $config = config("order_integrations.couriers.{$shipment->provider}");
            if (! is_array($config) || empty($config['enabled'])) {
                throw new \RuntimeException('This courier is not enabled in configuration.');
            }
            if (empty($shipment->order->customer_phone)) {
                throw new \RuntimeException('A customer phone is required before booking a courier.');
            }

            $order = $shipment->order;
            $response = match ($shipment->provider) {
                'steadfast' => $this->bookSteadfast($config, $order, $shipment),
                'redx' => $this->bookRedx($config, $order, $shipment),
                'pathao' => $this->bookPathao($config, $order, $shipment),
                'custom', 'carrybee' => $this->bookCustom($config, $order, $shipment),
                default => throw new \RuntimeException('Unsupported courier provider.'),
            };
            $tracking = data_get($response, 'consignment.tracking_code') ?? data_get($response, 'tracking_code') ?? data_get($response, 'data.tracking_id') ?? data_get($response, 'tracking_id');
            $providerId = data_get($response, 'consignment.consignment_id') ?? data_get($response, 'data.consignment_id') ?? data_get($response, 'data.id') ?? data_get($response, 'id');
            $attempts[$attemptIndex]['finished_at'] = now()->toISOString();
            $attempts[$attemptIndex]['outcome'] = 'booked';
            $metadata['retry_attempts'] = $attempts;
            $shipment->update([
                'status' => 'booked',
                'tracking_code' => $tracking ? (string) $tracking : null,
                'provider_shipment_id' => $providerId ? (string) $providerId : null,
                'provider_response' => $response,
                'booked_at' => now(),
                'last_error' => null,
                'metadata' => $metadata,
            ]);
        } catch (Throwable $exception) {
            $freshShipment = $shipment->fresh();
            $freshMetadata = $freshShipment?->metadata;
            if (! is_array($freshMetadata)) {
                $freshMetadata = [];
            }
            $freshAttempts = $freshMetadata['retry_attempts'] ?? [];
            if (! is_array($freshAttempts)) {
                $freshAttempts = [];
            }
            if (isset($freshAttempts[$attemptIndex]) && is_array($freshAttempts[$attemptIndex])) {
                $freshAttempts[$attemptIndex]['finished_at'] = now()->toISOString();
                $freshAttempts[$attemptIndex]['outcome'] = 'failed';
                $freshAttempts[$attemptIndex]['error'] = mb_substr($exception->getMessage(), 0, 1000);
            }
            $freshMetadata['retry_attempts'] = $freshAttempts;
            $shipment->update([
                'status' => 'failed',
                'metadata' => $freshMetadata,
                'last_error' => mb_substr($exception->getMessage(), 0, 2000),
            ]);
            throw ValidationException::withMessages(['courier' => 'Courier retry failed. Reconcile the provider record before another retry.']);
        }

        return $shipment->refresh();
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function bookSteadfast(array $config, SalesOrder $order, SalesOrderShipment $shipment): array
    {
        $response = Http::timeout((int) ($config['timeout'] ?? 15))->acceptJson()->withHeaders(['Api-Key' => $config['api_key'], 'Secret-Key' => $config['secret_key']])->post(rtrim($config['base_url'], '/').'/create_order', [
            'invoice' => $order->order_number, 'recipient_name' => $shipment->recipient_name,
            'recipient_phone' => $this->bdPhone($shipment->recipient_phone), 'recipient_address' => mb_substr($shipment->recipient_address, 0, 250),
            'cod_amount' => (float) $shipment->cod_amount, 'note' => 'Madina Scale order '.$order->order_number,
        ]);
        if (! $response->successful()) {
            throw new \RuntimeException('Steadfast returned HTTP '.$response->status());
        }
        $body = $response->json() ?? [];
        if (data_get($body, 'status') === 'error') {
            throw new \RuntimeException('Steadfast rejected the shipment.');
        }

        return $body;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function bookRedx(array $config, SalesOrder $order, SalesOrderShipment $shipment): array
    {
        if (empty($config['pickup_store_id'])) {
            throw new \RuntimeException('REDX pickup store ID is not configured.');
        }
        $response = Http::timeout((int) ($config['timeout'] ?? 15))->acceptJson()->withHeaders(['API-ACCESS-TOKEN' => 'Bearer '.$config['access_token']])->post(rtrim($config['base_url'], '/').'/parcel', [
            'customer_name' => $shipment->recipient_name, 'customer_phone' => $this->bdPhone($shipment->recipient_phone),
            'delivery_area' => isset($shipment->metadata['delivery_area']) ? (int) $shipment->metadata['delivery_area'] : null,
            'customer_address' => $shipment->recipient_address, 'merchant_invoice_id' => $order->order_number,
            'cash_collection_amount' => (int) round((float) $shipment->cod_amount), 'parcel_weight' => 500,
            'instruction' => 'Madina Scale order '.$order->order_number, 'pickup_store_id' => (int) $config['pickup_store_id'],
        ]);
        if (! $response->successful()) {
            throw new \RuntimeException('REDX returned HTTP '.$response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function bookPathao(array $config, SalesOrder $order, SalesOrderShipment $shipment): array
    {
        if (empty($config['base_url']) || empty($config['store_id']) || empty($config['access_token'])) {
            throw new \RuntimeException('Pathao base URL, access token and store ID must be configured.');
        }
        $response = Http::timeout((int) ($config['timeout'] ?? 15))->acceptJson()->withToken($config['access_token'])->post(rtrim($config['base_url'], '/').'/aladdin/api/v1/orders', [
            'store_id' => (int) $config['store_id'], 'merchant_order_id' => $order->order_number,
            'recipient_name' => $shipment->recipient_name, 'recipient_phone' => $this->bdPhone($shipment->recipient_phone),
            'recipient_address' => $shipment->recipient_address, 'delivery_type' => 48, 'item_type' => 2,
            'special_instruction' => 'Madina Scale order '.$order->order_number, 'item_quantity' => max(1, (int) $order->items()->sum('quantity')),
            'item_weight' => '0.5', 'item_description' => 'Order '.$order->order_number,
            'amount_to_collect' => (int) round((float) $shipment->cod_amount),
        ]);
        if (! $response->successful()) {
            throw new \RuntimeException('Pathao returned HTTP '.$response->status());
        }

        return $response->json() ?? [];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function bookCustom(array $config, SalesOrder $order, SalesOrderShipment $shipment): array
    {
        if (empty($config['base_url'])) {
            throw new \RuntimeException('Custom courier base URL is not configured.');
        }
        $request = Http::timeout((int) ($config['timeout'] ?? 15))->acceptJson();
        if (! empty($config['api_token'])) {
            $request = $request->withToken($config['api_token']);
        }
        $response = $request->post(rtrim($config['base_url'], '/').'/'.ltrim($config['create_path'] ?? '/shipments', '/'), [
            'merchant_order_id' => $order->order_number, 'recipient_name' => $shipment->recipient_name,
            'recipient_phone' => $this->bdPhone($shipment->recipient_phone), 'recipient_address' => $shipment->recipient_address,
            'cod_amount' => (float) $shipment->cod_amount,
        ]);
        if (! $response->successful()) {
            throw new \RuntimeException('Configured courier returned HTTP '.$response->status());
        }

        return $response->json() ?? [];
    }

    private function bdPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?: '';
        if (str_starts_with($digits, '880') && strlen($digits) === 13) {
            return '0'.substr($digits, 3);
        }
        if (str_starts_with($digits, '88') && strlen($digits) === 13) {
            return '0'.substr($digits, 2);
        }

        return $digits;
    }
}
