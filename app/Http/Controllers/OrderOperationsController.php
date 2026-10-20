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
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class OrderOperationsController extends Controller
{
    public function __construct(private readonly LocationInventoryAccessService $locationAccess, private readonly OrderRiskService $risk, private readonly OrderSmsService $sms, private readonly OrderPhoneVerificationService $verification, private readonly CourierIntegrationService $couriers) {}

    public function assessRisk(Request $request, SalesOrder $order): JsonResponse
    {
        $this->locationAccess->assertView($request->user(), (int) $order->location_id);
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
            'cod_amount' => ['nullable', 'numeric', 'min:0', 'max:' . max(0, (float) $order->grand_total)],
            'delivery_area' => ['nullable', 'integer', 'min:1'],
        ]);
        if (!in_array($order->status, ['confirmed', 'processing', 'partially_fulfilled', 'fulfilled'], true)) {
            return response()->json(['message' => 'Confirm the order before booking a courier shipment.'], 422);
        }
        if ($data['provider'] === 'redx' && empty($data['delivery_area'])) {
            return response()->json(['message' => 'REDX requires a valid delivery area ID from your merchant account.'], 422);
        }
        if (!empty($data['delivery_area'])) $data['metadata']['delivery_area'] = $data['delivery_area'];
        $shipment = $this->couriers->book($order, $data['provider'], $data, (int) $request->user()->id);
        if ($order->customer_phone) $this->sms->send($order, 'shipment_booked', 'আপনার অর্ডার ' . $order->order_number . ' কুরিয়ারে বুক করা হয়েছে। ট্র্যাকিং: ' . ($shipment->tracking_code ?: 'কুরিয়ার ট্র্যাকিং আপডেট অপেক্ষমাণ।'));
        return response()->json(['message' => 'Shipment booking processed.', 'data' => $shipment], 201);
    }

    public function courierWebhook(Request $request, string $provider): JsonResponse
    {
        $secret = (string) config('order_integrations.webhook_secret', '');
        $provided = (string) $request->bearerToken();
        if ($secret === '' || !hash_equals($secret, $provided)) abort(401);
        abort_unless(in_array($provider, ['steadfast', 'redx', 'pathao', 'carrybee', 'custom'], true), 404);
        $request->merge(['status' => $request->input('status', $request->input('delivery_status'))]);
        $data = $request->validate([
            'tracking_code' => ['required_without:consignment_id', 'nullable', 'string', 'max:160'], 'consignment_id' => ['required_without:tracking_code', 'nullable', 'string', 'max:120'],
            'status' => ['required', 'string', 'max:40'], 'delivered_at' => ['nullable', 'date'],
        ]);
        $shipment = SalesOrderShipment::query()->where('provider', $provider)->where(function ($query) use ($data): void {
            if (!empty($data['tracking_code'])) $query->orWhere('tracking_code', $data['tracking_code']);
            if (!empty($data['consignment_id'])) $query->orWhere('provider_shipment_id', $data['consignment_id']);
        })->first();
        if (!$shipment) return response()->json(['message' => 'Shipment not found.'], 404);
        $previousStatus = $shipment->status;
        $normalized = strtolower(str_replace([' ', '-'], '_', $data['status']));
        $map = ['delivered' => 'delivered', 'completed' => 'delivered', 'in_transit' => 'in_transit', 'picked' => 'in_transit', 'picked_up' => 'in_transit', 'cancelled' => 'cancelled', 'canceled' => 'cancelled', 'returned' => 'returned', 'return_to_origin' => 'returned', 'partial_delivered' => 'partially_delivered', 'partial_delivery' => 'partially_delivered', 'failed' => 'delivery_failed', 'pending' => 'booked', 'in_review' => 'booked'];
        $status = $map[$normalized] ?? 'status_unknown';
        $shipment->update(['status' => $status, 'delivered_at' => $status === 'delivered' ? ($data['delivered_at'] ?? now()) : $shipment->delivered_at, 'provider_response' => $request->all()]);
        if ($status === 'delivered' && $previousStatus !== 'delivered' && $shipment->order?->customer_phone) $this->sms->sendOrderEvent($shipment->order, 'delivered');
        return response()->json(['message' => 'Webhook accepted.', 'data' => ['shipment_id' => $shipment->id, 'status' => $shipment->status]]);
    }
}
