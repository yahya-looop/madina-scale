<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthorizationService;
use App\Services\LocationInventoryAccessService;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class BusinessAnalyticsController extends Controller
{
    public function __construct(
        private readonly LocationInventoryAccessService $locations,
        private readonly AuthorizationService $authorization,
    ) {}

    public function page(Request $request, string $view = 'overview'): InertiaResponse
    {
        abort_unless($request->user()?->can('system.analytics.view'), 403);

        return Inertia::render('Analytics/Index', ['view' => $view]);
    }

    public function data(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $from = $validated['from'] ?? now()->startOfMonth()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();
        $locationId = isset($validated['location_id']) ? (int) $validated['location_id'] : null;
        $hasGlobalLocationAccess = $this->authorization->can($user, 'system.locations.manage');
        $isGlobalScope = $hasGlobalLocationAccess && $locationId === null;

        if ($locationId !== null) {
            $this->locations->assertView($user, $locationId);
        }

        $allOrders = DB::table('sales_orders');
        $this->scope($allOrders, $user, $locationId, 'sales_orders.location_id');

        $orders = (clone $allOrders)->whereBetween('sales_orders.created_at', [
            $from.' 00:00:00',
            $to.' 23:59:59',
        ]);

        $validOrders = (clone $orders)->whereNotIn('sales_orders.status', ['cancelled', 'canceled', 'rejected']);
        $orderCount = (clone $validOrders)->count();
        $revenue = (float) (clone $validOrders)->sum('sales_orders.grand_total');

        $daily = (clone $validOrders)
            ->selectRaw('DATE(sales_orders.created_at) as date, COUNT(*) as orders, COALESCE(SUM(sales_orders.grand_total), 0) as revenue')
            ->groupByRaw('DATE(sales_orders.created_at)')
            ->orderByRaw('DATE(sales_orders.created_at)')
            ->get();

        $status = (clone $orders)
            ->select('sales_orders.status')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('sales_orders.status')
            ->orderByDesc('count')
            ->get();

        // Customer records are global master data. For a location-scoped user,
        // expose only customers linked to orders in locations they can view.
        $customers = $isGlobalScope
            ? DB::table('customers')->count()
            : (clone $allOrders)
                ->whereNotNull('sales_orders.customer_id')
                ->distinct()
                ->count('sales_orders.customer_id');

        $campaignMetrics = $this->campaignMetrics(
            $from,
            $to,
            $user,
            $locationId,
            $isGlobalScope,
        );

        return response()->json([
            'range' => ['from' => $from, 'to' => $to],
            'scope' => [
                'type' => $isGlobalScope ? 'global' : ($locationId !== null ? 'single_location' : 'assigned_locations'),
                'campaign_impressions_clicks_available' => $isGlobalScope,
            ],
            'summary' => [
                'revenue' => $revenue,
                'orders' => $orderCount,
                'customers' => $customers,
                'average_order_value' => $orderCount ? round($revenue / $orderCount, 2) : 0,
                ...$campaignMetrics,
            ],
            'daily' => $daily,
            'order_status' => $status,
        ]);
    }

    /**
     * @return array{
     *     campaigns: int|null,
     *     campaign_impressions: int|null,
     *     campaign_clicks: int|null,
     *     campaign_conversions: int,
     *     tracked_campaign_conversion_value: float
     * }
     */
    private function campaignMetrics(
        string $from,
        string $to,
        User $user,
        ?int $locationId,
        bool $isGlobalScope,
    ): array {
        $events = DB::table('marketing_campaign_events')
            ->whereBetween('marketing_campaign_events.occurred_at', [
                $from.' 00:00:00',
                $to.' 23:59:59',
            ]);

        if ($isGlobalScope) {
            $counts = (clone $events)
                ->select('event_type')
                ->selectRaw('COUNT(*) as total')
                ->groupBy('event_type')
                ->pluck('total', 'event_type');

            return [
                'campaigns' => DB::table('marketing_campaigns')->count(),
                'campaign_impressions' => (int) ($counts['impression'] ?? 0),
                'campaign_clicks' => (int) ($counts['click'] ?? 0),
                'campaign_conversions' => (int) ($counts['conversion'] ?? 0),
                'tracked_campaign_conversion_value' => (float) (clone $events)
                    ->where('event_type', 'conversion')
                    ->sum('value'),
            ];
        }

        // Impressions and clicks have no trusted location attribution in the
        // current schema. Do not leak global totals or mislabel them as local.
        // Conversion events can be scoped safely through their linked order.
        $attributedConversions = (clone $events)
            ->join('sales_orders', 'sales_orders.id', '=', 'marketing_campaign_events.order_id')
            ->where('marketing_campaign_events.event_type', 'conversion')
            ->whereNotNull('marketing_campaign_events.order_id');
        $this->scope($attributedConversions, $user, $locationId, 'sales_orders.location_id');

        return [
            'campaigns' => (clone $attributedConversions)->distinct()->count('marketing_campaign_events.campaign_id'),
            'campaign_impressions' => null,
            'campaign_clicks' => null,
            'campaign_conversions' => (clone $attributedConversions)->count(),
            'tracked_campaign_conversion_value' => (float) (clone $attributedConversions)
                ->sum('marketing_campaign_events.value'),
        ];
    }

    private function scope(Builder $query, User $user, ?int $locationId, string $column): void
    {
        if ($locationId !== null) {
            $query->where($column, $locationId);

            return;
        }

        $this->locations->scopeViewable($query, $user, $column);
    }
}
