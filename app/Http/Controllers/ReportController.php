<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LocationInventoryAccessService;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly LocationInventoryAccessService $locations) {}

    public function page(Request $request)
    {
        abort_unless($request->user()?->can('system.reports.view'), 403);

        return Inertia::render('Reports/Index', [
            'locations' => $this->locations->viewableLocations($request->user()),
            'canExport' => $request->user()->can('system.reports.export'),
            'defaultFrom' => now()->startOfMonth()->toDateString(),
            'defaultTo' => now()->toDateString(),
        ]);
    }

    public function data(Request $request)
    {
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ]);
        abort_if(Carbon::parse($validated['from'])->diffInDays(Carbon::parse($validated['to'])) > 366, 422, 'Report range cannot exceed 367 days.');
        $user = $request->user();
        $locationId = isset($validated['location_id']) ? (int) $validated['location_id'] : null;
        if ($locationId) {
            $this->locations->assertView($user, $locationId);
        }

        return response()->json($this->buildReport($user, $validated['from'], $validated['to'], $locationId));
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()?->can('system.reports.view') && $request->user()?->can('system.reports.export'), 403);
        $validated = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
            'dataset' => ['required', 'in:daily_sales,top_products,order_status,finance,inventory,delivery,returns,customers'],
        ]);
        abort_if(Carbon::parse($validated['from'])->diffInDays(Carbon::parse($validated['to'])) > 366, 422);
        $locationId = isset($validated['location_id']) ? (int) $validated['location_id'] : null;
        if ($locationId) {
            $this->locations->assertView($request->user(), $locationId);
        }
        $report = $this->buildReport($request->user(), $validated['from'], $validated['to'], $locationId);
        $rows = $report[$validated['dataset']] ?? [];
        $filename = 'madina-scale-'.$validated['dataset'].'-'.$validated['from'].'-'.$validated['to'].'.csv';

        return response()->streamDownload(function () use ($rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            if (count($rows) === 0) {
                fputcsv($out, ['No records for selected filters']);
            } else {
                fputcsv($out, array_keys((array) $rows[0]));
                foreach ($rows as $row) {
                    $values = array_values((array) $row);
                    $values = array_map(static function ($value) {
                        if (is_string($value) && preg_match('/^[=+\\-@\\t\\r]/', $value)) {
                            return "'".$value;
                        }

                        return $value;
                    }, $values);
                    fputcsv($out, $values);
                }
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function buildReport(User $user, string $from, string $to, ?int $locationId): array
    {
        $sales = DB::table('sales_orders')->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
        $this->scope($sales, $user, $locationId, 'sales_orders.location_id');
        $salesBase = clone $sales;
        $orders = (clone $salesBase)->whereNotIn('status', ['cancelled', 'canceled', 'rejected']);
        $orderCount = (clone $orders)->count();
        $grossSales = (float) (clone $orders)->sum('grand_total');
        $cancelledCount = (clone $salesBase)->whereIn('status', ['cancelled', 'canceled', 'rejected'])->count();

        $dailySales = (clone $orders)->selectRaw('DATE(created_at) as date, COUNT(*) as orders, COALESCE(SUM(grand_total),0) as sales')
            ->groupByRaw('DATE(created_at)')->orderByRaw('DATE(created_at)')->get()
            ->map(fn ($r) => ['date' => (string) $r->date, 'orders' => (int) $r->orders, 'sales' => (float) $r->sales])->all();

        $orderStatus = (clone $salesBase)->select('status')->selectRaw('COUNT(*) as orders, COALESCE(SUM(grand_total),0) as sales')
            ->groupBy('status')->orderByDesc('orders')->get()
            ->map(fn ($r) => ['status' => (string) $r->status, 'orders' => (int) $r->orders, 'sales' => (float) $r->sales])->all();

        $topProductsQuery = DB::table('sales_order_items')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_items.sales_order_id')
            ->whereBetween('sales_orders.created_at', [$from.' 00:00:00', $to.' 23:59:59'])
            ->whereNotIn('sales_orders.status', ['cancelled', 'canceled', 'rejected']);
        $this->scope($topProductsQuery, $user, $locationId, 'sales_orders.location_id');
        $topProducts = $topProductsQuery->select('sales_order_items.product_name', 'sales_order_items.sku')
            ->selectRaw('SUM(sales_order_items.quantity) as quantity, SUM(sales_order_items.line_total) as sales')
            ->groupBy('sales_order_items.product_name', 'sales_order_items.sku')
            ->orderByDesc('sales')->limit(10)->get()
            ->map(fn ($r) => ['product' => $r->product_name, 'sku' => $r->sku, 'quantity' => (float) $r->quantity, 'sales' => (float) $r->sales])->all();

        $journal = DB::table('journal_entry_lines')
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->join('financial_accounts', 'financial_accounts.id', '=', 'journal_entry_lines.account_id')
            ->where('journal_entries.status', 'posted')
            ->whereBetween('journal_entries.entry_date', [$from, $to]);
        $this->scope($journal, $user, $locationId, 'journal_entry_lines.location_id');
        $finance = $journal->select('financial_accounts.code', 'financial_accounts.name', 'financial_accounts.type', 'financial_accounts.normal_balance')
            ->selectRaw('SUM(journal_entry_lines.debit) as debit, SUM(journal_entry_lines.credit) as credit')
            ->groupBy('financial_accounts.id', 'financial_accounts.code', 'financial_accounts.name', 'financial_accounts.type', 'financial_accounts.normal_balance')
            ->orderBy('financial_accounts.code')->get()
            ->map(fn ($r) => ['code' => $r->code, 'account' => $r->name, 'type' => $r->type, 'debit' => (float) $r->debit, 'credit' => (float) $r->credit, 'net' => (float) ($r->normal_balance === 'debit' ? $r->debit - $r->credit : $r->credit - $r->debit)])->all();
        $income = array_sum(array_map(fn ($r) => $r['type'] === 'income' ? $r['credit'] - $r['debit'] : 0, $finance));
        $expense = array_sum(array_map(fn ($r) => $r['type'] === 'expense' ? $r['debit'] - $r['credit'] : 0, $finance));

        $inventoryQuery = DB::table('inventory_stocks')
            ->leftJoin('products', 'products.id', '=', 'inventory_stocks.product_id')
            ->leftJoin('product_variants', 'product_variants.id', '=', 'inventory_stocks.product_variant_id');
        $this->scope($inventoryQuery, $user, $locationId, 'inventory_stocks.location_id');
        $inventory = $inventoryQuery->selectRaw("COALESCE(products.name, product_variants.name, 'Unassigned product') as product")
            ->selectRaw('COALESCE(products.sku, product_variants.sku) as sku')
            ->selectRaw('SUM(inventory_stocks.quantity) as on_hand, SUM(inventory_stocks.reserved_quantity) as reserved')
            ->groupByRaw("COALESCE(products.name, product_variants.name, 'Unassigned product'), COALESCE(products.sku, product_variants.sku)")
            ->orderBy('product')->limit(500)->get()
            ->map(fn ($r) => ['product' => $r->product, 'sku' => $r->sku, 'on_hand' => (float) $r->on_hand, 'reserved' => (float) $r->reserved, 'available' => (float) $r->on_hand - (float) $r->reserved])->all();

        $shipmentQuery = DB::table('sales_order_shipments')
            ->join('sales_orders', 'sales_orders.id', '=', 'sales_order_shipments.sales_order_id')
            ->whereBetween('sales_orders.created_at', [$from.' 00:00:00', $to.' 23:59:59']);
        $this->scope($shipmentQuery, $user, $locationId, 'sales_orders.location_id');
        $delivery = $shipmentQuery->select('sales_order_shipments.provider', 'sales_order_shipments.status')
            ->selectRaw('COUNT(*) as shipments, COALESCE(SUM(sales_order_shipments.cod_amount),0) as cod')
            ->groupBy('sales_order_shipments.provider', 'sales_order_shipments.status')
            ->orderBy('sales_order_shipments.provider')->get()
            ->map(fn ($r) => ['provider' => $r->provider, 'status' => $r->status, 'shipments' => (int) $r->shipments, 'cod' => (float) $r->cod])->all();

        $returnQuery = DB::table('sales_returns')->whereBetween('return_date', [$from, $to]);
        $this->scope($returnQuery, $user, $locationId, 'sales_returns.location_id');
        $returns = $returnQuery->select('refund_method')->selectRaw('COUNT(*) as returns, COALESCE(SUM(refund_amount),0) as refund')
            ->groupBy('refund_method')->get()
            ->map(fn ($r) => ['method' => $r->refund_method, 'returns' => (int) $r->returns, 'refund' => (float) $r->refund])->all();

        $customerQuery = DB::table('sales_orders')->whereNotNull('customer_id')
            ->whereBetween('created_at', [$from.' 00:00:00', $to.' 23:59:59']);
        $this->scope($customerQuery, $user, $locationId, 'sales_orders.location_id');
        $customers = $customerQuery->select('customer_id')->selectRaw('COUNT(*) as orders, SUM(grand_total) as sales')
            ->groupBy('customer_id')->orderByDesc('sales')->limit(10)->get()
            ->map(function ($r) {
                $customer = DB::table('customers')->where('id', $r->customer_id)->first(['name', 'customer_code']);

                return ['customer' => $customer?->name ?? 'Unknown customer', 'code' => $customer?->customer_code, 'orders' => (int) $r->orders, 'sales' => (float) $r->sales];
            })->all();

        return [
            'meta' => ['from' => $from, 'to' => $to, 'location_id' => $locationId, 'currency' => 'BDT', 'generated_at' => now()->toIso8601String()],
            'kpis' => ['orders' => $orderCount, 'gross_sales' => $grossSales, 'average_order_value' => $orderCount ? $grossSales / $orderCount : 0, 'cancelled_orders' => $cancelledCount, 'posted_income' => $income, 'posted_expense' => $expense, 'net_operating_result' => $income - $expense, 'returns_count' => array_sum(array_column($returns, 'returns')), 'refund_total' => array_sum(array_column($returns, 'refund'))],
            'daily_sales' => $dailySales, 'top_products' => $topProducts, 'order_status' => $orderStatus,
            'finance' => $finance, 'inventory' => $inventory, 'delivery' => $delivery, 'returns' => $returns, 'customers' => $customers,
        ];
    }

    private function scope(Builder $query, User $user, ?int $locationId, string $column): void
    {
        if ($locationId) {
            $query->where($column, $locationId);
        } else {
            $this->locations->scopeViewable($query, $user, $column);
        }
    }
}
