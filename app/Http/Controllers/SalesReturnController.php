<?php

namespace App\Http\Controllers;

use App\Models\FinancialAccount;
use App\Models\SalesOrder;
use App\Models\SalesReturn;
use App\Services\LocationInventoryAccessService;
use App\Services\SalesReturnService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SalesReturnController extends Controller
{
    public function __construct(private readonly SalesReturnService $returns, private readonly LocationInventoryAccessService $locationAccess) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);
        $query = SalesReturn::query()->with(['order:id,order_number,customer_name', 'location:id,name', 'customer:id,name', 'cashAccount:id,code,name', 'journalEntry:id,entry_number,status', 'items.orderItem:id,product_name,variant_name,sku'])
            ->when(! $request->filled('location_id'), fn ($q) => $this->locationAccess->scopeViewable($q, $request->user()))
            ->when($request->filled('location_id'), function ($q) use ($request): void {
                $id = $request->integer('location_id');
                $this->locationAccess->assertView($request->user(), $id);
                $q->where('location_id', $id);
            })->latest('return_date')->latest('id');
        $page = $query->paginate($perPage)->withQueryString();

        return response()->json(['data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
    }

    public function options(Request $request): JsonResponse
    {
        $manageableLocationIds = $this->locationAccess->viewableLocations($request->user())
            ->where('access_level', 'manage')->pluck('id')->all();
        $orders = SalesOrder::query()->with(['items.fulfillmentItems.fulfillment', 'items.reservation'])
            ->whereIn('status', ['processing', 'completed'])
            ->whereIn('location_id', $manageableLocationIds)
            ->when(! $request->filled('location_id'), fn ($q) => $this->locationAccess->scopeViewable($q, $request->user()))
            ->when($request->filled('location_id'), function ($q) use ($request): void {
                $id = $request->integer('location_id');
                $this->locationAccess->assertManage($request->user(), $id);
                $q->where('location_id', $id);
            })->latest('id')->limit(100)->get();
        $orders->each(function (SalesOrder $order): void {
            $order->items->each(function ($item): void {
                $fulfilled = $item->fulfillmentItems->filter(fn ($line) => $line->fulfillment?->status === 'completed')->sum(fn ($line) => (float) $line->quantity);
                $returned = (float) \DB::table('sales_return_items as sri')->join('sales_returns as sr', 'sr.id', '=', 'sri.sales_return_id')->where('sri.sales_order_item_id', $item->id)->where('sr.status', 'completed')->sum('sri.quantity');
                $item->setAttribute('returnable_quantity', max(0, $fulfilled - $returned));
            });
        });

        return response()->json(['data' => $orders, 'cash_accounts' => FinancialAccount::query()->where('is_active', true)->where('is_cash_bank', true)->orderBy('code')->get(['id', 'code', 'name']), 'locations' => $this->locationAccess->viewableLocations($request->user())->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'sales_order_id' => ['required', 'integer', 'exists:sales_orders,id'],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'return_date' => ['required', 'date'],
            'refund_method' => ['required', Rule::in(['cash', 'bank', 'store_credit'])],
            'cash_account_id' => ['nullable', 'integer', 'exists:financial_accounts,id'],
            'reason' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sales_order_item_id' => ['required', 'integer', 'distinct', 'exists:sales_order_items,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'items.*.disposition' => ['sometimes', 'string', Rule::in(['restock', 'damaged', 'quarantine', 'rejected'])],
            'items.*.disposition_note' => ['nullable', 'string', 'max:500'],
        ]);
        if ($data['refund_method'] !== 'store_credit' && empty($data['cash_account_id'])) {
            return response()->json(['message' => 'A cash or bank account is required for cash/bank refunds.'], 422);
        }
        $return = $this->returns->create($data, $request->user());

        return response()->json(['message' => 'Sales return recorded and refund posted. Only restock dispositions affect sellable inventory.', 'data' => $return], $return->wasRecentlyCreated ? 201 : 200);
    }
}
