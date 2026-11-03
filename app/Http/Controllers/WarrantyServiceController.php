<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\FinancialAccount;
use App\Models\JournalEntry;
use App\Models\Location;
use App\Models\ProductVariant;
use App\Models\SalesOrderItem;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\FinancialLedgerService;
use App\Services\InventoryService;
use App\Services\LocationInventoryAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class WarrantyServiceController extends Controller
{
    public function __construct(
        private readonly LocationInventoryAccessService $locationAccess,
        private readonly ActivityLogService $activityLog,
        private readonly InventoryService $inventory,
        private readonly FinancialLedgerService $ledger,
    ) {}

    public function page(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->can('system.warranty.view'), 403);

        return Inertia::render('Warranty/Index', [
            'userName' => $user->name,
            'userEmail' => $user->email,
            'canCreate' => $user->can('system.warranty.create'),
            'canUpdate' => $user->can('system.warranty.update'),
            'canProcess' => $user->can('system.warranty.process'),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $locationId = $request->integer('location_id');
        $term = trim($request->string('search')->toString());

        $warranties = DB::table('product_warranties as w')
            ->join('sales_order_items as soi', 'soi.id', '=', 'w.sales_order_item_id')
            ->join('sales_orders as so', 'so.id', '=', 'w.sales_order_id')
            ->join('customers as c', 'c.id', '=', 'w.customer_id')
            ->join('locations as l', 'l.id', '=', 'w.location_id')
            ->when($locationId > 0, function ($q) use ($request, $locationId): void {
                $this->locationAccess->assertView($request->user(), $locationId);
                $q->where('w.location_id', $locationId);
            }, fn ($q) => $this->locationAccess->scopeViewable($q, $request->user(), 'w.location_id'))
            ->when($term !== '', fn ($q) => $q->where(function ($sub) use ($term): void {
                $sub->where('w.serial_number', 'like', "%{$term}%")
                    ->orWhere('w.warranty_number', 'like', "%{$term}%")
                    ->orWhere('c.name', 'like', "%{$term}%")
                    ->orWhere('so.order_number', 'like', "%{$term}%")
                    ->orWhere('soi.sku', 'like', "%{$term}%");
            }))
            ->orderByDesc('w.id')
            ->select([
                'w.*', DB::raw("CASE WHEN w.status = 'active' AND w.warranty_end < CURRENT_DATE THEN 'expired' ELSE w.status END AS display_status"), 'c.name as customer_name', 'c.phone as customer_phone',
                'l.name as location_name', 'so.order_number', 'soi.product_name',
                'soi.variant_name', 'soi.sku',
            ])->paginate($perPage)->withQueryString();

        $tickets = DB::table('service_tickets as t')
            ->leftJoin('product_warranties as w', 'w.id', '=', 't.warranty_id')
            ->join('customers as c', 'c.id', '=', 't.customer_id')
            ->join('locations as l', 'l.id', '=', 't.location_id')
            ->leftJoin('users as u', 'u.id', '=', 't.assigned_to')
            ->when($locationId > 0, function ($q) use ($request, $locationId): void {
                $this->locationAccess->assertView($request->user(), $locationId);
                $q->where('t.location_id', $locationId);
            }, fn ($q) => $this->locationAccess->scopeViewable($q, $request->user(), 't.location_id'))
            ->when($term !== '', fn ($q) => $q->where(function ($sub) use ($term): void {
                $sub->where('t.ticket_number', 'like', "%{$term}%")
                    ->orWhere('t.issue', 'like', "%{$term}%")
                    ->orWhere('c.name', 'like', "%{$term}%");
            }))
            ->orderByDesc('t.id')
            ->select(['t.*', 'c.name as customer_name', 'c.phone as customer_phone',
                'l.name as location_name', 'u.name as technician_name', 'w.warranty_number'])
            ->paginate($perPage, ['*'], 'tickets_page')->withQueryString();

        $summary = [
            'active_warranties' => DB::table('product_warranties')->where('status', 'active')
                ->whereDate('warranty_end', '>=', now()->toDateString())
                ->whereIn('location_id', $this->locationAccess->viewableLocations($request->user())->pluck('id'))->count(),
            'open_tickets' => DB::table('service_tickets')->whereNotIn('status', ['closed', 'rejected'])
                ->whereIn('location_id', $this->locationAccess->viewableLocations($request->user())->pluck('id'))->count(),
            'in_repair' => DB::table('service_tickets')->whereIn('status', ['diagnosing', 'waiting_parts', 'in_repair'])
                ->whereIn('location_id', $this->locationAccess->viewableLocations($request->user())->pluck('id'))->count(),
        ];

        return response()->json([
            'warranties' => ['data' => $warranties->items(), 'meta' => ['current_page' => $warranties->currentPage(), 'last_page' => $warranties->lastPage(), 'total' => $warranties->total()]],
            'tickets' => ['data' => $tickets->items(), 'meta' => ['current_page' => $tickets->currentPage(), 'last_page' => $tickets->lastPage(), 'total' => $tickets->total()]],
            'summary' => $summary,
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $locations = $this->locationAccess->viewableLocations($request->user())->values();
        $manageableLocationIds = $locations->where('access_level', 'manage')->pluck('id')->all();
        $orderItems = SalesOrderItem::query()
            ->with(['order:id,order_number,customer_id,customer_name,location_id,status', 'order.customer:id,name', 'product:id,name'])
            ->whereHas('order', fn ($q) => $q->whereIn('status', ['fulfilled', 'completed'])->whereNotNull('customer_id'))
            ->withCount('warranties')
            ->whereHas('order', fn ($q) => $q->whereIn('location_id', $manageableLocationIds))
            ->latest('id')->limit(500)->get()
            ->filter(fn (SalesOrderItem $item): bool => (int) $item->warranties_count < (int) floor((float) $item->quantity))
            ->map(fn (SalesOrderItem $item): array => [
                'id' => $item->id,
                'label' => trim(($item->order?->order_number ?? '').' — '.$item->product_name.' '.($item->variant_name ?? '').' ('.$item->sku.')'),
                'sales_order_id' => $item->sales_order_id,
                'order_number' => $item->order?->order_number,
                'customer_id' => $item->order?->customer_id,
                'customer_name' => $item->order?->customer?->name ?? $item->order?->customer_name,
                'location_id' => $item->order?->location_id,
                'quantity' => (string) $item->quantity,
            ]);

        $technicians = User::query()->where('status', 'active')
            ->when(! $request->user()->can('system.locations.manage'), fn ($q) => $q->whereHas('locations', fn ($locations) => $locations
                ->whereIn('locations.id', $manageableLocationIds)
                ->whereIn('location_user.access_level', ['view', 'receive', 'manage'])))
            ->orderBy('name')->get(['id', 'name', 'email']);
        $parts = ProductVariant::query()->with('product:id,name')->where('status', 'active')->orderBy('product_id')->orderBy('name')->limit(500)->get(['id', 'product_id', 'name', 'sku'])
            ->map(fn (ProductVariant $variant): array => ['id' => $variant->id, 'label' => trim(($variant->product?->name ?? 'Product').' — '.($variant->name ?? '').' ('.$variant->sku.')')]);
        $customers = Customer::query()->whereHas('orders', fn ($q) => $this->locationAccess->scopeViewable($q, $request->user()))
            ->orderBy('name')->limit(500)->get(['id', 'name', 'phone']);

        return response()->json(['locations' => $locations, 'order_items' => $orderItems, 'technicians' => $technicians, 'parts' => $parts, 'customers' => $customers]);
    }

    public function registerWarranty(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('system.warranty.create'), 403);
        $data = $request->validate([
            'sales_order_item_id' => ['required', 'integer', 'exists:sales_order_items,id'],
            'serial_number' => ['nullable', 'string', 'max:120', 'unique:product_warranties,serial_number'],
            'warranty_months' => ['required', 'integer', 'min:1', 'max:120'],
            'warranty_start' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $warranty = DB::transaction(function () use ($request, $data) {
            $item = SalesOrderItem::query()->with('order')->lockForUpdate()->findOrFail($data['sales_order_item_id']);
            $order = $item->order;
            abort_unless($order && in_array($order->status, ['fulfilled', 'completed'], true), 422, 'Warranty can only be registered for fulfilled or completed sales order items.');
            $this->locationAccess->assertManage($request->user(), (int) $order->location_id);
            $registeredUnits = DB::table('product_warranties')->where('sales_order_item_id', $item->id)->count();
            if ($registeredUnits >= (int) floor((float) $item->quantity)) {
                throw ValidationException::withMessages(['sales_order_item_id' => 'All units on this sales order item already have warranty registrations.']);
            }

            $start = Carbon::parse($data['warranty_start'])->startOfDay();
            $end = $start->copy()->addMonthsNoOverflow((int) $data['warranty_months'])->subDay();
            $id = DB::table('product_warranties')->insertGetId([
                'warranty_number' => 'WAR-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'sales_order_id' => $order->id,
                'sales_order_item_id' => $item->id,
                'customer_id' => $order->customer_id,
                'location_id' => $order->location_id,
                'serial_number' => $data['serial_number'] ?? null,
                'warranty_start' => $start->toDateString(),
                'warranty_end' => $end->toDateString(),
                'status' => 'active',
                'notes' => $data['notes'] ?? null,
                'registered_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $warranty = DB::table('product_warranties')->where('id', $id)->first();
            $this->activityLog->recordForUser($request->user(), 'registered', 'warranty', 'Registered warranty '.$warranty->warranty_number, null, null, (array) $warranty);

            return $warranty;
        }, 3);

        return response()->json(['message' => 'Warranty registered.', 'data' => $warranty], 201);
    }

    public function createTicket(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('system.warranty.create'), 403);
        $data = $request->validate([
            'warranty_id' => ['nullable', 'integer', 'exists:product_warranties,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'issue' => ['required', 'string', 'min:5', 'max:5000'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'received_at' => ['required', 'date'],
        ]);

        $this->locationAccess->assertManage($request->user(), (int) $data['location_id']);
        if (! empty($data['warranty_id'])) {
            $warranty = DB::table('product_warranties')->where('id', $data['warranty_id'])->first();
            abort_unless($warranty && (int) $warranty->customer_id === (int) $data['customer_id'] && (int) $warranty->location_id === (int) $data['location_id'], 422, 'Warranty, customer and location must match.');
        }
        if (! empty($data['assigned_to'])) {
            $this->assertTechnicianCanWorkAtLocation((int) $data['assigned_to'], (int) $data['location_id']);
        }

        $ticket = DB::transaction(function () use ($request, $data) {
            $id = DB::table('service_tickets')->insertGetId([
                'ticket_number' => 'SRV-'.now()->format('ymd').'-'.Str::upper(Str::random(6)),
                'warranty_id' => $data['warranty_id'] ?? null,
                'customer_id' => $data['customer_id'],
                'location_id' => $data['location_id'],
                'assigned_to' => $data['assigned_to'] ?? null,
                'status' => 'open',
                'priority' => $data['priority'],
                'issue' => trim($data['issue']),
                'diagnosis' => null,
                'resolution' => null,
                'received_at' => $data['received_at'],
                'resolved_at' => null,
                'created_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $ticket = DB::table('service_tickets')->where('id', $id)->first();
            $this->activityLog->recordForUser($request->user(), 'created', 'warranty', 'Created service ticket '.$ticket->ticket_number, null, null, (array) $ticket);

            return $ticket;
        }, 3);

        return response()->json(['message' => 'Service ticket created.', 'data' => $ticket], 201);
    }

    public function addPart(Request $request, int $ticket): JsonResponse
    {
        abort_unless($request->user()->can('system.warranty.process'), 403);
        $data = $request->validate([
            'product_variant_id' => ['required', 'integer', 'exists:product_variants,id'],
            'quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
        ]);

        $result = DB::transaction(function () use ($request, $ticket, $data) {
            $row = DB::table('service_tickets')->where('id', $ticket)->lockForUpdate()->first();
            abort_unless($row, 404);
            $this->locationAccess->assertManage($request->user(), (int) $row->location_id);
            if (in_array($row->status, ['closed', 'rejected'], true)) {
                throw ValidationException::withMessages(['ticket' => 'Parts cannot be issued to a closed or rejected ticket.']);
            }
            // The project uses ProductVariant.cost_price as its existing unit-cost source.
            // Never issue inventory or post an expense when that source is missing/zero.
            $variant = ProductVariant::query()->lockForUpdate()->findOrFail((int) $data['product_variant_id']);
            $unitCost = bcadd((string) $variant->cost_price, '0', 2);
            if (bccomp($unitCost, '0.00', 2) <= 0) {
                throw ValidationException::withMessages([
                    'product_variant_id' => 'This spare part has no valid cost price. Set its existing product cost before issuing it.',
                ]);
            }
            $quantity = bcadd((string) $data['quantity'], '0', 4);
            $totalCost = bcadd(bcmul($unitCost, $quantity, 6), '0', 2);
            if (bccomp($totalCost, '0.00', 2) <= 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'The calculated spare-parts cost rounds to zero; adjust the quantity or cost price.',
                ]);
            }

            $movement = $this->inventory->issue([
                'product_variant_id' => (int) $data['product_variant_id'],
                'location_id' => (int) $row->location_id,
                'quantity' => $quantity,
                'reason' => 'Service ticket parts usage',
                'reference_type' => 'service_ticket',
                'reference_id' => (string) $row->id,
                'idempotency_key' => $data['idempotency_key'],
                'metadata' => ['ticket_number' => $row->ticket_number],
            ], $request->user());

            $existing = DB::table('service_ticket_parts')->where('stock_movement_id', $movement->id)->first();
            if ($existing) {
                return $existing;
            }

            $inventoryAccount = FinancialAccount::query()->where('code', '1200')->where('is_active', true)->first();
            $serviceExpenseAccount = FinancialAccount::query()->where('code', '6200')->where('is_active', true)->first();
            if (! $inventoryAccount || ! $serviceExpenseAccount) {
                throw ValidationException::withMessages([
                    'accounting' => 'Required Inventory Asset (1200) or Service Parts Expense (6200) account is unavailable. Run pending migrations and verify the chart of accounts.',
                ]);
            }

            $journal = JournalEntry::query()->create([
                'entry_number' => 'JE-SRV-'.now()->format('ymdHis').'-'.Str::upper(Str::random(5)),
                'entry_date' => now()->toDateString(),
                'description' => 'Service parts issued for '.$row->ticket_number,
                'status' => 'draft',
                'source' => 'service_parts',
                'reference' => $row->ticket_number,
                'location_id' => $row->location_id,
                'idempotency_key' => 'service-parts:'.$movement->id,
                'created_by' => $request->user()->id,
            ]);
            $journal->lines()->createMany([
                [
                    'account_id' => $serviceExpenseAccount->id,
                    'location_id' => $row->location_id,
                    'description' => 'Service parts expense — '.$variant->sku,
                    'debit' => $totalCost,
                    'credit' => '0.00',
                ],
                [
                    'account_id' => $inventoryAccount->id,
                    'location_id' => $row->location_id,
                    'description' => 'Inventory issued — '.$variant->sku,
                    'debit' => '0.00',
                    'credit' => $totalCost,
                ],
            ]);
            $this->ledger->post($journal, $request->user());

            $id = DB::table('service_ticket_parts')->insertGetId([
                'service_ticket_id' => $row->id,
                'product_variant_id' => $data['product_variant_id'],
                'quantity' => $quantity,
                'stock_movement_id' => $movement->id,
                'unit_cost' => $unitCost,
                'total_cost' => $totalCost,
                'journal_entry_id' => $journal->id,
                'notes' => $data['notes'] ?? null,
                'recorded_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $part = DB::table('service_ticket_parts')->where('id', $id)->first();
            $this->activityLog->recordForUser($request->user(), 'issued', 'warranty', 'Issued spare part for '.$row->ticket_number, null, null, (array) $part);

            return $part;
        }, 3);

        return response()->json(['message' => 'Spare part issued from location inventory.', 'data' => $result]);
    }

    public function updateTicket(Request $request, int $ticket): JsonResponse
    {
        abort_unless($request->user()->can('system.warranty.update') || $request->user()->can('system.warranty.process'), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['open', 'diagnosing', 'waiting_parts', 'in_repair', 'resolved', 'closed', 'rejected'])],
            'priority' => ['sometimes', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'diagnosis' => ['nullable', 'string', 'max:5000'],
            'resolution' => ['nullable', 'string', 'max:5000'],
        ]);

        $updated = DB::transaction(function () use ($request, $ticket, $data) {
            $row = DB::table('service_tickets')->where('id', $ticket)->lockForUpdate()->first();
            abort_unless($row, 404);
            $this->locationAccess->assertManage($request->user(), (int) $row->location_id);
            $old = (array) $row;
            if (in_array($data['status'], ['resolved', 'closed'], true) && trim((string) ($data['resolution'] ?? $row->resolution ?? '')) === '') {
                throw ValidationException::withMessages(['resolution' => 'A resolution note is required before resolving or closing a ticket.']);
            }
            if (! empty($data['assigned_to'])) {
                $this->assertTechnicianCanWorkAtLocation((int) $data['assigned_to'], (int) $row->location_id);
            }
            $changes = [
                'status' => $data['status'],
                'priority' => $data['priority'] ?? $row->priority,
                'assigned_to' => array_key_exists('assigned_to', $data) ? $data['assigned_to'] : $row->assigned_to,
                'diagnosis' => $data['diagnosis'] ?? $row->diagnosis,
                'resolution' => $data['resolution'] ?? $row->resolution,
                'resolved_at' => in_array($data['status'], ['resolved', 'closed'], true) ? ($row->resolved_at ?? now()) : null,
                'updated_at' => now(),
            ];
            DB::table('service_tickets')->where('id', $ticket)->update($changes);
            $new = (array) DB::table('service_tickets')->where('id', $ticket)->first();
            $this->activityLog->recordForUser($request->user(), 'updated', 'warranty', 'Updated service ticket '.$row->ticket_number, null, $old, $new);

            return $new;
        }, 3);

        return response()->json(['message' => 'Service ticket updated.', 'data' => $updated]);
    }

    /** A technician must be active and assigned to the ticket's location. */
    private function assertTechnicianCanWorkAtLocation(int $technicianId, int $locationId): void
    {
        $technician = User::query()->whereKey($technicianId)->where('status', 'active')->first();
        abort_unless($technician, 422, 'Assigned technician is not active.');

        // A global location manager may work across all locations. Other users
        // must have an explicit location assignment; a view-only assignment is
        // sufficient for technician assignment, while ticket processing itself
        // remains protected by the actor's manage permission.
        abort_unless(
            $technician->can('system.locations.manage')
                || $technician->locations()->where('locations.id', $locationId)
                    ->whereIn('location_user.access_level', ['view', 'receive', 'manage'])->exists(),
            422,
            'Assigned technician is not assigned to this service location.'
        );
    }
}
