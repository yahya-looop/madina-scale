<?php

namespace App\Http\Controllers\Api\V1\Storefront;

use App\Http\Controllers\Controller;
use App\Models\SalesOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PublicOrderTrackingController extends Controller
{
    public function track(Request $request): JsonResponse
    {
        $data = $request->validate([
            'reference' => ['required', 'string', 'min:3', 'max:160'],
            'phone' => ['required', 'string', 'min:8', 'max:40'],
        ]);

        $reference = trim($data['reference']);
        $orders = SalesOrder::query()
            ->where(function ($query) use ($reference): void {
                $query->where('order_number', $reference)
                    ->orWhereHas('shipments', fn ($shipmentQuery) => $shipmentQuery->where('tracking_code', $reference));
            })
            ->with([
                'items:id,sales_order_id,product_name,variant_name,quantity',
                'statusHistory:id,sales_order_id,from_status,to_status,created_at',
                'shipments:id,sales_order_id,provider,tracking_code,status,booked_at,delivered_at,created_at',
            ])
            ->get();

        // A single generic failure response avoids revealing whether an order reference exists.
        // Match after phone normalization so +880 and local 0-prefixed formats are equivalent.
        $normalizedPhone = $this->normalizePhone($data['phone']);
        $order = $orders->first(fn (SalesOrder $candidate) => $this->normalizePhone((string) $candidate->customer_phone) === $normalizedPhone);
        if (! $order) {
            return response()->json(['message' => 'We could not verify this order. Check the reference and mobile number, then try again.'], 404)
                ->header('Cache-Control', 'no-store');
        }

        $shipmentIds = $order->shipments->pluck('id')->all();
        $shipmentEvents = $shipmentIds === []
            ? collect()
            : DB::table('sales_order_shipment_events')
                ->whereIn('sales_order_shipment_id', $shipmentIds)
                ->where('outcome', 'applied')
                ->orderBy('received_at')
                ->get(['id', 'sales_order_shipment_id', 'provider', 'received_status', 'received_at'])
                ->groupBy('sales_order_shipment_id');

        $timeline = $order->statusHistory
            ->map(fn ($history) => [
                'type' => 'order',
                'status' => $history->to_status,
                'occurred_at' => $history->created_at,
            ])
            ->sortBy('occurred_at')
            ->values();

        if ($timeline->isEmpty()) {
            $timeline->push([
                'type' => 'order',
                'status' => $order->status,
                'occurred_at' => $order->created_at,
            ]);
        }

        $shipments = $order->shipments->map(function ($shipment) use ($shipmentEvents): array {
            $events = ($shipmentEvents->get($shipment->id) ?? collect())->map(fn ($event) => [
                'status' => $event->received_status,
                'occurred_at' => $event->received_at,
            ])->values();

            return [
                'provider' => $shipment->provider,
                'tracking_code' => $shipment->tracking_code,
                'status' => $shipment->status,
                'booked_at' => $shipment->booked_at,
                'delivered_at' => $shipment->delivered_at,
                'events' => $events,
            ];
        })->values();

        return response()->json([
            'data' => [
                'order_number' => $order->order_number,
                'status' => $order->status,
                'placed_at' => $order->created_at,
                'updated_at' => $order->updated_at,
                'items' => $order->items->map(fn ($item) => [
                    'product_name' => $item->product_name,
                    'variant_name' => $item->variant_name,
                    'quantity' => $item->quantity,
                ])->values(),
                'timeline' => $timeline,
                'shipments' => $shipments,
            ],
        ])->header('Cache-Control', 'no-store');
    }

    private function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';
        if (str_starts_with($digits, '880')) {
            $digits = '0'.substr($digits, 3);
        }

        return $digits;
    }
}
