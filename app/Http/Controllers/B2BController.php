<?php

namespace App\Http\Controllers;

use App\Models\B2BAccount;
use App\Models\B2BQuote;
use App\Models\Customer;
use App\Models\Lead;
use App\Services\SalesOrderService;
use App\Services\LocationInventoryAccessService;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class B2BController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = B2BAccount::query()->with(['customer:id,customer_code,name', 'lead:id,lead_code,name,status'])
            ->withCount('quotes')->withSum('quotes as quoted_value', 'total')->search($request->string('search')->toString());
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->validate(['status' => ['required', Rule::in(['pending', 'active', 'on_hold', 'inactive'])]])['status']);
        }
        if ($request->filled('tier') && $request->input('tier') !== 'all') {
            $query->where('tier', $request->validate(['tier' => ['required', Rule::in(['standard', 'silver', 'gold', 'platinum'])]])['tier']);
        }
        $stats = [
            'accounts' => B2BAccount::count(),
            'active' => B2BAccount::where('status', 'active')->count(),
            'pending' => B2BAccount::where('status', 'pending')->count(),
            'quoted_value' => (float) B2BQuote::whereIn('status', ['draft', 'sent', 'under_review'])->sum('total'),
            'open_quotes' => B2BQuote::whereIn('status', ['draft', 'sent', 'under_review'])->count(),
        ];
        $accounts = $query->latest('id')->paginate(min(max($request->integer('per_page', 15), 1), 50))->withQueryString();
        return response()->json(array_merge($accounts->toArray(), ['stats' => $stats]));
    }

    public function options(): JsonResponse
    {
        return response()->json([
            'customers' => Customer::query()->where('status', 'active')->orderBy('name')->get(['id', 'customer_code', 'name', 'company_name']),
            'leads' => Lead::query()->where('customer_type', 'b2b')->whereNull('customer_id')->whereNotIn('status', ['converted', 'lost'])->latest()->limit(100)->get(['id', 'lead_code', 'name', 'phone', 'email']),
            'variants' => ProductVariant::query()->with('product:id,name')->where('status', 'active')->orderBy('product_id')->limit(500)->get(['id', 'product_id', 'name', 'sku', 'selling_price', 'wholesale_price']),
        ]);
    }

    public function show(B2BAccount $account): JsonResponse
    {
        $account->load(['customer:id,customer_code,name,phone,email', 'lead:id,lead_code,name,status', 'quotes' => fn ($q) => $q->withCount('items')->latest()->limit(20)]);
        return response()->json(['data' => $account]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        if (!empty($data['customer_id']) && B2BAccount::where('customer_id', $data['customer_id'])->exists()) {
            throw ValidationException::withMessages(['customer_id' => 'This customer is already linked to a B2B account.']);
        }
        if (!empty($data['lead_id']) && B2BAccount::where('lead_id', $data['lead_id'])->exists()) {
            throw ValidationException::withMessages(['lead_id' => 'This lead is already linked to a B2B account.']);
        }
        $data['account_code'] = $this->newAccountCode();
        $data['created_by'] = $request->user()->id;
        $data['updated_by'] = $request->user()->id;
        $account = B2BAccount::create($data);
        return response()->json(['message' => 'B2B account created successfully.', 'data' => $account], 201);
    }

    public function update(Request $request, B2BAccount $account): JsonResponse
    {
        $data = $this->validated($request, $account);
        foreach (['customer_id', 'lead_id'] as $field) {
            if (!empty($data[$field]) && B2BAccount::where($field, $data[$field])->where('id', '<>', $account->id)->exists()) {
                throw ValidationException::withMessages([$field => 'This record is already linked to another B2B account.']);
            }
        }
        $data['updated_by'] = $request->user()->id;
        $account->update($data);
        return response()->json(['message' => 'B2B account updated successfully.', 'data' => $account->fresh()]);
    }

    public function destroy(B2BAccount $account): JsonResponse
    {
        if ($account->quotes()->exists()) {
            throw ValidationException::withMessages(['account' => 'This account has quote history and cannot be deleted. Change its status to inactive instead.']);
        }
        $account->delete();
        return response()->json(['message' => 'B2B account deleted successfully.']);
    }

    public function quotes(Request $request): JsonResponse
    {
        $query = B2BQuote::query()->with('account:id,account_code,company_name')->withCount('items');
        if ($request->filled('search')) {
            $term = trim($request->string('search')->toString());
            $query->where(fn (Builder $q) => $q->where('quote_number', 'like', "%{$term}%")
                ->orWhereHas('account', fn (Builder $a) => $a->where('company_name', 'like', "%{$term}%")));
        }
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->validate(['status' => ['required', Rule::in(['draft', 'sent', 'under_review', 'accepted', 'rejected', 'expired'])]])['status']);
        }
        return response()->json(array_merge($query->latest('id')->paginate(15)->withQueryString()->toArray(), [
            'stats' => ['total' => B2BQuote::count(), 'draft' => B2BQuote::where('status', 'draft')->count(), 'sent' => B2BQuote::whereIn('status', ['sent', 'under_review'])->count(), 'accepted' => B2BQuote::where('status', 'accepted')->count()],
        ]));
    }

    public function storeQuote(Request $request): JsonResponse
    {
        $data = $request->validate([
            'b2b_account_id' => ['required', 'integer', Rule::exists('b2b_accounts', 'id')->where(fn ($q) => $q->where('status', 'active'))],
            'currency' => ['required', 'string', 'size:3'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:today'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'tax_amount' => ['nullable', 'numeric', 'min:0'],
            'terms' => ['nullable', 'string', 'max:5000'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_variant_id' => ['nullable', 'integer', 'exists:product_variants,id'],
            'items.*.description' => ['required', 'string', 'max:240'],
            'items.*.sku' => ['nullable', 'string', 'max:100'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:1000000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999'],
            'items.*.discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);
        $quote = DB::transaction(function () use ($data, $request): B2BQuote {
            $quote = B2BQuote::create([
                'quote_number' => $this->newQuoteNumber(), 'b2b_account_id' => $data['b2b_account_id'],
                'status' => 'draft', 'currency' => strtoupper($data['currency']), 'valid_until' => $data['valid_until'] ?? null,
                'discount_amount' => $data['discount_amount'] ?? 0, 'tax_amount' => $data['tax_amount'] ?? 0,
                'terms' => $data['terms'] ?? null, 'notes' => $data['notes'] ?? null, 'created_by' => $request->user()->id,
            ]);
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $lineGross = (float) $item['quantity'] * (float) $item['unit_price'];
                $lineTotal = round($lineGross * (1 - ((float) ($item['discount_percent'] ?? 0) / 100)), 2);
                $subtotal += $lineTotal;
                $quote->items()->create([
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'sku' => $item['sku'] ?? null,
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'discount_percent' => $item['discount_percent'] ?? 0,
                    'line_total' => $lineTotal,
                ]);
            }
            $discount = (float) ($data['discount_amount'] ?? 0);
            if ($discount > $subtotal) throw ValidationException::withMessages(['discount_amount' => 'The quote discount cannot exceed the item subtotal.']);
            $quote->update(['subtotal' => round($subtotal, 2), 'total' => round($subtotal - $discount + (float) ($data['tax_amount'] ?? 0), 2)]);
            return $quote->load(['account:id,account_code,company_name', 'items']);
        });
        return response()->json(['message' => 'Quotation draft created successfully.', 'data' => $quote], 201);
    }

    public function showQuote(B2BQuote $quote): JsonResponse
    {
        return response()->json(['data' => $quote->load(['account', 'items'])]);
    }

    public function updateQuoteStatus(Request $request, B2BQuote $quote): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['sent', 'under_review', 'accepted', 'rejected', 'expired'])]]);

        $updated = DB::transaction(function () use ($quote, $data): B2BQuote {
            $locked = B2BQuote::query()->lockForUpdate()->findOrFail($quote->id);

            if ($locked->converted_order_id) {
                throw ValidationException::withMessages(['status' => 'A quotation converted to an order can no longer be changed.']);
            }

            $allowed = [
                'draft' => ['sent'],
                'sent' => ['under_review', 'accepted', 'rejected', 'expired'],
                'under_review' => ['accepted', 'rejected', 'expired'],
            ];

            if ($locked->valid_until?->isBefore(today()) && in_array($data['status'], ['sent', 'accepted'], true)) {
                throw ValidationException::withMessages(['status' => 'An expired quotation cannot be sent or accepted.']);
            }

            if (! in_array($data['status'], $allowed[$locked->status] ?? [], true)) {
                throw ValidationException::withMessages(['status' => "Cannot move a {$locked->status} quotation to {$data['status']}."]);
            }

            $locked->status = $data['status'];
            if ($data['status'] === 'accepted') {
                $locked->accepted_at = now();
            }
            $locked->save();

            return $locked->fresh(['account', 'items']);
        }, 3);

        return response()->json(['message' => 'Quotation status updated.', 'data' => $updated]);
    }

    /**
     * Convert an accepted B2B quotation into exactly one inventory-reserving sales order.
     * The quotation's commercial line prices are snapshotted onto the resulting order.
     */
    public function convertQuoteToOrder(
        Request $request,
        B2BQuote $quote,
        SalesOrderService $orders,
        LocationInventoryAccessService $locationAccess,
    ): JsonResponse {
        $data = $request->validate([
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'idempotency_key' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $locationAccess->assertManage($request->user(), (int) $data['location_id']);

        $order = DB::transaction(function () use ($request, $quote, $orders, $data): \App\Models\SalesOrder {
            $lockedQuote = B2BQuote::query()->with(['account', 'items'])->lockForUpdate()->findOrFail($quote->id);

            $keyedOrder = \App\Models\SalesOrder::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->lockForUpdate()
                ->first();
            if ($keyedOrder && (int) $keyedOrder->b2b_quote_id !== (int) $lockedQuote->id) {
                throw ValidationException::withMessages([
                    'idempotency_key' => 'This request key is already associated with a different order. Use a new key for this quotation.',
                ]);
            }

            if ($lockedQuote->converted_order_id) {
                $existingOrder = \App\Models\SalesOrder::query()->with(['items', 'customer'])->findOrFail($lockedQuote->converted_order_id);
                if ((int) $existingOrder->b2b_quote_id !== (int) $lockedQuote->id
                    || (int) $existingOrder->b2b_account_id !== (int) $lockedQuote->b2b_account_id) {
                    throw ValidationException::withMessages(['quote' => 'The linked order does not match this quotation. Contact an administrator.']);
                }

                return $existingOrder;
            }
            if ($lockedQuote->status !== 'accepted') {
                throw ValidationException::withMessages(['quote' => 'Only accepted quotations can be converted into orders.']);
            }
            if ($lockedQuote->valid_until && $lockedQuote->valid_until->isBefore(today())) {
                throw ValidationException::withMessages(['quote' => 'This quotation has expired.']);
            }
            $account = B2BAccount::query()->lockForUpdate()->findOrFail($lockedQuote->b2b_account_id);
            if ($account->status !== 'active') {
                throw ValidationException::withMessages(['account' => 'The business account must be active to place an order.']);
            }
            if (! $account->customer_id) {
                throw ValidationException::withMessages(['account' => 'Link this business account to a customer before converting its quotation.']);
            }
            if ($lockedQuote->items->contains(fn ($item) => ! $item->product_variant_id)) {
                throw ValidationException::withMessages(['items' => 'Every quotation line must be linked to an active product variant before order conversion.']);
            }

            $outstanding = (float) \App\Models\SalesOrder::query()
                ->where('b2b_account_id', $account->id)
                ->whereIn('status', ['pending', 'confirmed', 'processing', 'partially_fulfilled'])
                ->sum('grand_total');
            $availableCredit = max(0, (float) $account->credit_limit - $outstanding);
            if ($account->payment_terms !== 'prepaid' && (float) $lockedQuote->total > $availableCredit) {
                throw ValidationException::withMessages(['credit_limit' => 'The quotation exceeds the available B2B credit limit.']);
            }

            $customer = Customer::query()->lockForUpdate()->findOrFail($account->customer_id);
            $lines = $lockedQuote->items->map(fn ($item) => [
                'product_variant_id' => (int) $item->product_variant_id,
                'quantity' => (float) $item->quantity,
            ])->all();

            $order = $orders->create([
                'location_id' => $data['location_id'],
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_email' => $customer->email,
                'customer_phone' => $customer->phone,
                'channel' => 'backoffice',
                'notes' => trim(($data['notes'] ?? '')."\nB2B quotation ".$lockedQuote->quote_number),
                'idempotency_key' => $data['idempotency_key'],
                'items' => $lines,
            ], $request->user());

            $order->load(['items' => fn ($query) => $query->orderBy('id')]);
            if ($order->items->count() !== $lockedQuote->items->count()) {
                throw ValidationException::withMessages(['items' => 'The order lines do not match the accepted quotation. No conversion was completed.']);
            }

            // Replace catalog-derived provisional amounts with the accepted quote's
            // immutable commercial snapshots; stock reservations remain quantity-based.
            foreach ($lockedQuote->items as $index => $quoteItem) {
                $orderItem = $order->items->get($index);
                if (! $orderItem) {
                    throw ValidationException::withMessages(['items' => 'The order lines could not be matched to the accepted quotation.']);
                }
                $lineTotal = number_format((float) $quoteItem->line_total, 2, '.', '');
                $unitPrice = number_format((float) $quoteItem->unit_price, 2, '.', '');
                $orderItem->update(['unit_price' => $unitPrice, 'line_total' => $lineTotal]);
            }
            $order->update([
                'b2b_account_id' => $account->id,
                'b2b_quote_id' => $lockedQuote->id,
                'lead_id' => $account->lead_id,
                'source_type' => 'b2b_quote',
                'source_id' => $lockedQuote->id,
                'subtotal' => $lockedQuote->subtotal,
                'discount_total' => $lockedQuote->discount_amount,
                'tax_total' => $lockedQuote->tax_amount,
                'grand_total' => $lockedQuote->total,
            ]);
            $lockedQuote->update(['converted_order_id' => $order->id, 'converted_at' => now()]);
            if ($account->lead_id) {
                Lead::query()->whereKey($account->lead_id)->where('status', '!=', 'converted')->update([
                    'status' => 'converted', 'customer_id' => $customer->id, 'converted_order_id' => $order->id,
                    'updated_by' => $request->user()->id,
                ]);
            }
            return $order->fresh(['items', 'customer', 'b2bAccount', 'b2bQuote']);
        }, 3);

        return response()->json(['message' => 'Accepted quotation converted to sales order.', 'data' => $order], 201);
    }

    private function validated(Request $request, ?B2BAccount $account = null): array
    {
        return $request->validate([
            'company_name' => ['required', 'string', 'max:180'], 'legal_name' => ['nullable', 'string', 'max:180'],
            'business_type' => ['required', Rule::in(['retailer', 'distributor', 'reseller', 'corporate', 'institution', 'other'])],
            'tier' => ['required', Rule::in(['standard', 'silver', 'gold', 'platinum'])],
            'status' => ['required', Rule::in(['pending', 'active', 'on_hold', 'inactive'])],
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'], 'lead_id' => ['nullable', 'integer', 'exists:leads,id'],
            'contact_name' => ['nullable', 'string', 'max:160'], 'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\- .]{7,40}$/'],
            'tax_id' => ['nullable', 'string', 'max:100'], 'registration_number' => ['nullable', 'string', 'max:100'],
            'payment_terms' => ['required', Rule::in(['prepaid', 'cod', 'net_7', 'net_15', 'net_30', 'net_60'])],
            'credit_limit' => ['required', 'numeric', 'min:0', 'max:999999999999'], 'default_discount_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'billing_address' => ['nullable', 'string', 'max:500'], 'billing_area' => ['nullable', 'string', 'max:120'],
            'billing_district' => ['nullable', 'string', 'max:120'], 'billing_division' => ['nullable', 'string', 'max:120'], 'billing_postal_code' => ['nullable', 'string', 'max:20'],
            'shipping_address' => ['nullable', 'string', 'max:500'], 'shipping_area' => ['nullable', 'string', 'max:120'],
            'shipping_district' => ['nullable', 'string', 'max:120'], 'shipping_division' => ['nullable', 'string', 'max:120'], 'shipping_postal_code' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    private function newAccountCode(): string
    {
        do { $code = 'B2B-'.now()->format('ym').'-'.Str::upper(Str::random(6)); }
        while (B2BAccount::where('account_code', $code)->exists());
        return $code;
    }

    private function newQuoteNumber(): string
    {
        do { $number = 'QT-'.now()->format('ymd').'-'.Str::upper(Str::random(5)); }
        while (B2BQuote::where('quote_number', $number)->exists());
        return $number;
    }
}
