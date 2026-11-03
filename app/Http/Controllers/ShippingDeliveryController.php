<?php

namespace App\Http\Controllers;

use App\Models\SalesOrderShipment;
use App\Services\LocationInventoryAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ShippingDeliveryController extends Controller
{
    public function __construct(private readonly LocationInventoryAccessService $locations) {}

    public function page(Request $request): Response
    {
        abort_unless($request->user()->can('system.shipping.view'), 403);

        return Inertia::render('Shipping/Index', [
            'userName' => $request->user()->name,
            'userEmail' => $request->user()->email,
            'canUpdate' => $request->user()->can('system.shipping.delivery.manage'),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $locations = $this->locations->viewableLocations($user);
        $locationIds = $locations->pluck('id')->all();
        $query = SalesOrderShipment::query()->with(['order:id,order_number,location_id,customer_name,customer_phone,grand_total,status', 'creator:id,name'])
            ->whereHas('order', fn ($q) => $q->whereIn('location_id', $locationIds));

        if ($request->filled('location_id')) {
            $locationId = (int) $request->integer('location_id');
            $this->locations->assertView($user, $locationId);
            $query->whereHas('order', fn ($q) => $q->where('location_id', $locationId));
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }
        if ($request->filled('provider')) {
            $query->where('provider', $request->string('provider')->toString());
        }
        if ($request->filled('q')) {
            $term = trim($request->string('q')->toString());
            $query->where(function ($q) use ($term): void {
                $q->where('tracking_code', 'ilike', "%{$term}%")
                    ->orWhere('provider_shipment_id', 'ilike', "%{$term}%")
                    ->orWhereHas('order', fn ($o) => $o->where('order_number', 'ilike', "%{$term}%")->orWhere('customer_name', 'ilike', "%{$term}%"));
            });
        }

        $base = SalesOrderShipment::query()->whereHas('order', fn ($q) => $q->whereIn('location_id', $locationIds));
        if ($request->filled('location_id')) {
            $base->whereHas('order', fn ($q) => $q->where('location_id', (int) $request->integer('location_id')));
        }
        $counts = (clone $base)->selectRaw("count(*) as total, count(*) filter (where status in ('queued','booking','booked')) as pending, count(*) filter (where status = 'in_transit') as in_transit, count(*) filter (where status = 'delivered') as delivered, count(*) filter (where status in ('delivery_failed','failed')) as exceptions, count(*) filter (where status = 'returned') as returned")->first();

        return response()->json([
            'data' => $query->latest('id')->paginate(min(100, max(10, $request->integer('per_page', 25)))),
            'summary' => $counts,
            'locations' => $locations,
            'statuses' => ['queued', 'booking', 'booked', 'in_transit', 'partially_delivered', 'delivered', 'delivery_failed', 'returned', 'cancelled', 'failed'],
        ]);
    }

    public function events(Request $request, SalesOrderShipment $shipment): JsonResponse
    {
        $this->locations->assertView($request->user(), (int) $shipment->order()->value('location_id'));
        $events = DB::table('sales_order_shipment_events')->where('sales_order_shipment_id', $shipment->id)->latest('received_at')->limit(100)->get()->map(function ($event) {
            $event->payload = is_string($event->payload) ? (json_decode($event->payload, true) ?: []) : ($event->payload ?? []);

            return $event;
        });

        return response()->json(['data' => $events]);
    }

    public function updateStatus(Request $request, SalesOrderShipment $shipment): JsonResponse
    {
        $this->locations->assertManage($request->user(), (int) $shipment->order()->value('location_id'));
        $data = $request->validate([
            'status' => ['required', Rule::in(['booked', 'in_transit', 'partially_delivered', 'delivered', 'delivery_failed', 'returned', 'cancelled'])],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $updated = DB::transaction(function () use ($shipment, $data, $request) {
            $locked = SalesOrderShipment::query()->lockForUpdate()->findOrFail($shipment->id);
            $allowed = [
                'queued' => ['booked', 'delivery_failed', 'cancelled'], 'booking' => ['booked', 'delivery_failed', 'cancelled'],
                'booked' => ['in_transit', 'partially_delivered', 'delivered', 'delivery_failed', 'returned', 'cancelled'],
                'in_transit' => ['partially_delivered', 'delivered', 'delivery_failed', 'returned', 'cancelled'],
                'delivery_failed' => ['in_transit', 'partially_delivered', 'delivered', 'returned', 'cancelled'],
                'partially_delivered' => ['delivered', 'returned'], 'delivered' => ['returned'],
                'failed' => ['booked', 'delivery_failed', 'cancelled'], 'returned' => [], 'cancelled' => [],
            ];
            abort_unless(in_array($data['status'], $allowed[$locked->status] ?? [], true), 422, 'This delivery status transition is not allowed.');
            $previous = $locked->status;
            $locked->update(['status' => $data['status'], 'delivered_at' => $data['status'] === 'delivered' ? ($locked->delivered_at ?? now()) : $locked->delivered_at]);
            $hash = hash('sha256', 'manual|'.$locked->id.'|'.$previous.'|'.$data['status'].'|'.Str::uuid());
            DB::table('sales_order_shipment_events')->insert([
                'sales_order_shipment_id' => $locked->id, 'provider' => 'manual', 'event_hash' => $hash,
                'previous_status' => $previous, 'received_status' => $data['status'], 'outcome' => 'applied',
                'payload' => json_encode(['reason' => trim($data['reason']), 'actor_id' => $request->user()->id, 'actor_name' => $request->user()->name], JSON_THROW_ON_ERROR),
                'received_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);

            return $locked->fresh(['order:id,order_number,location_id,customer_name,customer_phone,grand_total,status']);
        });

        return response()->json(['message' => 'Delivery status updated and recorded in shipment history.', 'data' => $updated]);
    }
}
