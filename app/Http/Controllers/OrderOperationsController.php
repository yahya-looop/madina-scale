<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Models\SalesOrderRiskAssessment;
use App\Models\SalesOrderShipment;
use App\Services\CourierIntegrationService;
use App\Services\LocationInventoryAccessService;
use App\Services\OrderPhoneVerificationService;
use App\Services\OrderRiskService;
use App\Services\OrderSmsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class OrderOperationsController extends Controller
{
    public function __construct(private readonly LocationInventoryAccessService $locationAccess, private readonly OrderRiskService $risk, private readonly OrderSmsService $sms, private readonly OrderPhoneVerificationService $verification, private readonly CourierIntegrationService $couriers) {}

    public function assessRisk(Request $request, SalesOrder $order): JsonResponse
    {
        // Creating an assessment is a state-changing risk operation, not a read.
        $this->locationAccess->assertManage($request->user(), (int) $order->location_id);
        $assessment = $this->risk->assess($order);

        return response()->json(['message' => 'Risk assessment completed.', 'data' => $assessment]);
    }

    public function reviewRisk(Request $request, SalesOrderRiskAssessment $assessment): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $assessment->order->location_id);
        $data = $request->validate(['decision' => ['required', Rule::in(['allow', 'verify', 'manual_review', 'reject'])], 'review_note' => ['required', 'string', 'min:3', 'max:1000']]);
        $assessment->update(['decision' => $data['decision'], 'review_note' => trim($data['review_note']), 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

        return response()->json(['message' => 'Risk review saved.', 'data' => $assessment->refresh()]);
    }

    public function sendSms(Request $request, SalesOrder $order): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $order->location_id);
        $data = $request->validate(['event' => ['required', Rule::in(['confirmed', 'shipment_booked', 'delivered'])], 'message' => ['nullable', 'string', 'max:1000']]);
        $log = $data['message'] ?? null
            ? $this->sms->send($order, $data['event'], $data['message'])
            : $this->sms->sendOrderEvent($order, $data['event']);

        return response()->json(['message' => 'SMS request processed; check status for gateway delivery confirmation.', 'data' => $log], 202);
    }

    public function requestPhoneVerification(Request $request, SalesOrder $order): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $order->location_id);
        $this->verification->send($order);

        return response()->json(['message' => 'Verification code requested. Delivery depends on SMS gateway configuration.'], 202);
    }

    public function verifyPhone(Request $request, SalesOrder $order): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $order->location_id);
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $this->verification->verify($order, $data['code']);

        return response()->json(['message' => 'Customer phone verified.']);
    }

    public function shipments(Request $request, SalesOrder $order): JsonResponse
    {
        $this->locationAccess->assertView($request->user(), (int) $order->location_id);

        return response()->json(['data' => $order->shipments()->latest('id')->get()]);
    }

    public function bookShipment(Request $request, SalesOrder $order): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $order->location_id);
        $data = $request->validate([
            'provider' => ['required', Rule::in(['steadfast', 'redx', 'pathao', 'carrybee', 'custom'])],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'recipient_name' => ['nullable', 'string', 'max:160'], 'recipient_phone' => ['nullable', 'string', 'max:40'],
            'recipient_address' => ['required', 'string', 'max:500'],
            'cod_amount' => ['nullable', 'numeric', 'min:0', 'max:'.max(0, (float) $order->grand_total)],
            'delivery_area' => ['nullable', 'integer', 'min:1'],
        ]);
        if (! in_array($order->status, ['confirmed', 'processing', 'partially_fulfilled', 'fulfilled'], true)) {
            return response()->json(['message' => 'Confirm the order before booking a courier shipment.'], 422);
        }
        if ($data['provider'] === 'redx' && empty($data['delivery_area'])) {
            return response()->json(['message' => 'REDX requires a valid delivery area ID from your merchant account.'], 422);
        }
        if (! empty($data['delivery_area'])) {
            $data['metadata']['delivery_area'] = $data['delivery_area'];
        }
        // Handle idempotent replays before triggering any customer-facing side effects.
        // A previously failed/unfinished booking must never be represented as successful.
        $existingShipment = SalesOrderShipment::query()
            ->where('provider', $data['provider'])
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();

        if ($existingShipment) {
            if ((int) $existingShipment->sales_order_id !== (int) $order->id) {
                // Keep the service's canonical cross-order idempotency validation.
                $this->couriers->book($order, $data['provider'], $data, (int) $request->user()->id);
            }

            if ($existingShipment->status === 'failed') {
                return response()->json([
                    'message' => 'The previous courier booking failed. Review the shipment record before starting a controlled retry.',
                    'data' => $existingShipment,
                ], 422);
            }

            if ($existingShipment->status === 'booking') {
                return response()->json([
                    'message' => 'A courier booking attempt is already in progress or requires reconciliation. Do not retry it blindly.',
                    'data' => $existingShipment,
                ], 202);
            }

            if (! in_array($existingShipment->status, ['booked', 'in_transit', 'partially_delivered', 'delivered'], true)) {
                return response()->json([
                    'message' => 'The existing shipment is not in a state that can be treated as a successful booking.',
                    'data' => $existingShipment,
                ], 422);
            }

            return response()->json([
                'message' => 'Existing shipment returned; no new courier booking or SMS was created.',
                'data' => $existingShipment,
            ], 200);
        }

        $shipment = $this->couriers->book($order, $data['provider'], $data, (int) $request->user()->id);
        if ($shipment->status !== 'booked') {
            return response()->json([
                'message' => 'Courier booking did not reach a confirmed booked state.',
                'data' => $shipment,
            ], 422);
        }

        if ($order->customer_phone) {
            $this->sms->send($order, 'shipment_booked', 'আপনার অর্ডার '.$order->order_number.' কুরিয়ারে বুক করা হয়েছে। ট্র্যাকিং: '.($shipment->tracking_code ?: 'কুরিয়ার ট্র্যাকিং আপডেট অপেক্ষমাণ।'));
        }

        return response()->json(['message' => 'Shipment booking processed.', 'data' => $shipment], 201);
    }

    public function retryShipment(Request $request, SalesOrder $order, SalesOrderShipment $shipment): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $order->location_id);
        abort_unless((int) $shipment->sales_order_id === (int) $order->id, 404);

        $data = $request->validate([
            'confirm_no_existing_shipment' => ['required', 'accepted'],
            'retry_reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        // Serialize retry requests. Never start a second attempt while another request is booking.
        $locked = DB::transaction(function () use ($shipment): SalesOrderShipment {
            $locked = SalesOrderShipment::query()->lockForUpdate()->findOrFail($shipment->id);
            if ($locked->status !== 'failed') {
                abort(response()->json([
                    'message' => 'Only failed shipments can be retried; booking or successful shipments require reconciliation instead.',
                    'data' => $locked,
                ], $locked->status === 'booking' ? 202 : 422));
            }
            $locked->update(['status' => 'booking']);

            return $locked->fresh();
        });

        $retried = $this->couriers->retryFailed($locked, (int) $request->user()->id, trim($data['retry_reason']));
        if ($retried->status !== 'booked') {
            return response()->json(['message' => 'Courier retry did not reach a confirmed booked state.', 'data' => $retried], 422);
        }

        // Retry success is audited in shipment metadata; don't emit another booking SMS automatically.
        return response()->json(['message' => 'Courier retry succeeded. No additional booking SMS was sent.', 'data' => $retried], 200);
    }

    public function courierWebhook(Request $request, string $provider): JsonResponse
    {
        $secret = (string) config('order_integrations.webhook_secret', '');
        $provided = (string) $request->bearerToken();
        if ($secret === '' || ! hash_equals($secret, $provided)) {
            abort(401);
        }
        abort_unless(in_array($provider, ['steadfast', 'redx', 'pathao', 'carrybee', 'custom'], true), 404);

        $request->merge(['status' => $request->input('status', $request->input('delivery_status'))]);
        $data = $request->validate([
            'tracking_code' => ['required_without:consignment_id', 'nullable', 'string', 'max:160'],
            'consignment_id' => ['required_without:tracking_code', 'nullable', 'string', 'max:120'],
            'status' => ['required', 'string', 'max:40'],
            'delivered_at' => ['nullable', 'date'],
            'event_id' => ['nullable', 'string', 'max:160'],
        ]);

        $shipmentQuery = SalesOrderShipment::query()->where('provider', $provider)->where(function ($query) use ($data): void {
            if (! empty($data['tracking_code'])) {
                $query->orWhere('tracking_code', $data['tracking_code']);
            }
            if (! empty($data['consignment_id'])) {
                $query->orWhere('provider_shipment_id', $data['consignment_id']);
            }
        });
        $shipment = $shipmentQuery->first();
        if (! $shipment) {
            return response()->json(['message' => 'Shipment not found.'], 404);
        }

        $normalized = strtolower(str_replace([' ', '-'], '_', trim($data['status'])));
        $map = [
            'booked' => 'booked', 'pending' => 'booked', 'in_review' => 'booked',
            'in_transit' => 'in_transit', 'picked' => 'in_transit', 'picked_up' => 'in_transit',
            'delivered' => 'delivered', 'completed' => 'delivered',
            'cancelled' => 'cancelled', 'canceled' => 'cancelled',
            'returned' => 'returned', 'return_to_origin' => 'returned',
            'partial_delivered' => 'partially_delivered', 'partial_delivery' => 'partially_delivered',
            'failed' => 'delivery_failed', 'delivery_failed' => 'delivery_failed',
        ];
        if (! isset($map[$normalized])) {
            return response()->json(['message' => 'Unsupported courier status; no shipment changes were made.'], 422);
        }
        $status = $map[$normalized];
        // A provider event ID identifies one immutable event. Do not let a replay
        // with a changed status or payload become a second event. Providers that
        // omit event IDs retain deterministic payload-based deduplication.
        $eventHash = isset($data['event_id']) && $data['event_id'] !== ''
            ? hash('sha256', $provider.'|event|'.$data['event_id'])
            : hash('sha256', $provider.'|'.($data['event_id'] ?? '').'|'.($data['tracking_code'] ?? '').'|'.($data['consignment_id'] ?? '').'|'.$normalized.'|'.($data['delivered_at'] ?? ''));

        $result = DB::transaction(function () use ($shipment, $provider, $data, $status, $eventHash): array {
            $locked = SalesOrderShipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $previousStatus = (string) $locked->status;
            $allowed = [
                'booking' => ['booked', 'delivery_failed', 'cancelled'],
                'queued' => ['booked', 'delivery_failed', 'cancelled'],
                'booked' => ['booked', 'in_transit', 'delivery_failed', 'partially_delivered', 'delivered', 'returned', 'cancelled'],
                'in_transit' => ['in_transit', 'delivery_failed', 'partially_delivered', 'delivered', 'returned', 'cancelled'],
                'delivery_failed' => ['delivery_failed', 'in_transit', 'partially_delivered', 'delivered', 'returned', 'cancelled'],
                'partially_delivered' => ['partially_delivered', 'delivered', 'returned'],
                'delivered' => ['delivered', 'returned'],
                'returned' => ['returned'],
                'cancelled' => ['cancelled'],
                'failed' => ['booked', 'delivery_failed', 'cancelled'],
            ];
            $ignored = ! in_array($status, $allowed[$previousStatus] ?? [$previousStatus], true);
            $payload = json_encode($data, JSON_THROW_ON_ERROR);
            $now = now();

            // insertOrIgnore + the existing unique(provider, event_hash) index
            // makes deduplication safe even when concurrent callbacks target
            // different shipment rows with the same provider event ID.
            $inserted = DB::table('sales_order_shipment_events')->insertOrIgnore([
                'sales_order_shipment_id' => $locked->id,
                'provider' => $provider,
                'event_hash' => $eventHash,
                'event_id' => $data['event_id'] ?? null,
                'previous_status' => $previousStatus,
                'received_status' => $status,
                'outcome' => $ignored ? 'ignored_transition' : 'applied',
                'payload' => $payload,
                'received_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if ($inserted === 0) {
                return ['duplicate' => true, 'ignored' => false, 'shipment' => $locked, 'send_delivered_sms' => false];
            }

            if ($ignored) {
                return ['duplicate' => false, 'ignored' => true, 'shipment' => $locked, 'send_delivered_sms' => false];
            }

            $locked->update([
                'status' => $status,
                'delivered_at' => $status === 'delivered' ? ($data['delivered_at'] ?? $locked->delivered_at ?? now()) : $locked->delivered_at,
                'provider_response' => $data,
            ]);

            return [
                'duplicate' => false, 'ignored' => false, 'shipment' => $locked->fresh(),
                'send_delivered_sms' => $status === 'delivered' && $previousStatus !== 'delivered',
            ];
        });

        if (! empty($result['send_delivered_sms']) && $result['shipment']->order?->customer_phone) {
            $this->sms->sendOrderEvent($result['shipment']->order, 'delivered');
        }
        if (! empty($result['ignored'])) {
            return response()->json(['message' => 'Out-of-order courier event recorded and ignored.', 'data' => ['shipment_id' => $result['shipment']->id, 'status' => $result['shipment']->status]], 202);
        }

        return response()->json([
            'message' => ! empty($result['duplicate']) ? 'Duplicate courier event accepted.' : 'Webhook accepted.',
            'data' => ['shipment_id' => $result['shipment']->id, 'status' => $result['shipment']->status, 'duplicate' => (bool) $result['duplicate']],
        ]);
    }
}
