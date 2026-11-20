<?php

namespace App\Http\Controllers;

use App\Models\FinancialAccount;
use App\Models\FinancialTransaction;
use App\Models\SalesOrder;
use App\Services\FinancialLedgerService;
use App\Services\LocationInventoryAccessService;
use App\Services\SalesOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PaymentController extends Controller
{
    public function __construct(
        private readonly FinancialLedgerService $ledger,
        private readonly LocationInventoryAccessService $locationAccess,
        private readonly SalesOrderService $orders,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(max($request->integer('per_page', 15), 1), 100);

        $query = FinancialTransaction::query()
            ->where('type', 'customer_receipt')
            ->with(['customer:id,name,customer_code,phone', 'cashAccount:id,code,name', 'location:id,name', 'journalEntry:id,entry_number,status'])
            ->when($request->filled('from'), fn ($q) => $q->whereDate('transaction_date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($q) => $q->whereDate('transaction_date', '<=', $request->date('to')))
            ->when($request->filled('payment_method'), fn ($q) => $q->where('payment_method', $request->string('payment_method')->toString()))
            ->when($request->filled('search'), function ($q) use ($request): void {
                $term = '%'.str_replace(['%', '_'], ['\%', '\_'], trim($request->string('search')->toString())).'%';
                $q->where(function ($nested) use ($term): void {
                    $nested->where('transaction_number', 'like', $term)
                        ->orWhere('reference', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', $term)->orWhere('customer_code', 'like', $term));
                });
            })
            ->when($request->filled('location_id'), function ($q) use ($request): void {
                $id = $request->integer('location_id');
                $this->locationAccess->assertView($request->user(), $id);
                $q->where('location_id', $id);
            }, fn ($q) => $this->locationAccess->scopeViewable($q, $request->user()));

        /** @var LengthAwarePaginator<int, FinancialTransaction> $items */
        $items = $query->latest('transaction_date')->latest('id')->paginate($perPage)->withQueryString();
        $payments = $items->getCollection();
        $ids = $payments->modelKeys();
        $allocated = DB::table('payment_allocations')->whereIn('financial_transaction_id', $ids)
            ->select('financial_transaction_id')->selectRaw('SUM(amount) as allocated_amount')
            ->groupBy('financial_transaction_id')->pluck('allocated_amount', 'financial_transaction_id');

        $data = $payments->map(function (FinancialTransaction $payment) use ($allocated): array {
            $amount = (string) $payment->amount;
            /** @var numeric-string $used */
            $used = $this->decimal($allocated[$payment->id] ?? '0.00');

            return [
                'id' => $payment->id,
                'transaction_number' => $payment->transaction_number,
                'transaction_date' => $payment->transaction_date->toDateString(),
                'amount' => $amount,
                'allocated_amount' => $used,
                'unallocated_amount' => bcsub($amount, $used, 2),
                'payment_method' => $payment->payment_method,
                'reference' => $payment->reference,
                'description' => $payment->description,
                'customer' => $payment->customer,
                'cash_account' => $payment->cashAccount,
                'location' => $payment->location,
                'journal_entry' => $payment->journalEntry,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => ['current_page' => $items->currentPage(), 'last_page' => $items->lastPage(), 'per_page' => $items->perPage(), 'total' => $items->total()],
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        $locations = $this->locationAccess->viewableLocations($request->user())->values();
        $cashAccounts = FinancialAccount::query()->where('is_active', true)->where('is_cash_bank', true)->orderBy('code')->get(['id', 'code', 'name']);
        $orders = SalesOrder::query()
            ->with('customer:id,name,customer_code')
            ->whereIn('status', ['confirmed', 'processing', 'fulfilled', 'completed'])
            ->where('grand_total', '>', 0)
            ->when($request->filled('location_id'), function ($q) use ($request): void {
                $id = $request->integer('location_id');
                $this->locationAccess->assertView($request->user(), $id);
                $q->where('location_id', $id);
            }, fn ($q) => $this->locationAccess->scopeViewable($q, $request->user()))
            ->latest('id')->limit(300)->get(['id', 'order_number', 'customer_id', 'customer_name', 'grand_total', 'location_id']);

        $allocatedByOrder = DB::table('payment_allocations')->whereIn('sales_order_id', $orders->pluck('id'))
            ->select('sales_order_id')->selectRaw('SUM(amount) as paid_amount')
            ->groupBy('sales_order_id')->pluck('paid_amount', 'sales_order_id');

        return response()->json(['data' => [
            'locations' => $locations,
            'cash_accounts' => $cashAccounts,
            'orders' => $orders->map(fn (SalesOrder $order): array => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_id' => $order->customer_id,
                'customer_name' => $order->customer->name ?? $order->customer_name,
                'grand_total' => (string) $order->grand_total,
                'paid_amount' => $this->decimal($allocatedByOrder[$order->id] ?? '0.00'),
                'due_amount' => bcsub((string) $order->grand_total, $this->decimal($allocatedByOrder[$order->id] ?? '0.00'), 2),
                'location_id' => $order->location_id,
            ]),
        ]]);
    }

    public function storeReceipt(Request $request): JsonResponse
    {
        $data = $request->validate([
            'idempotency_key' => ['required', 'string', 'max:100'],
            'transaction_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'payment_method' => ['required', Rule::in(['cash', 'bank', 'mobile_banking', 'card', 'other'])],
            'cash_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'reference' => ['nullable', 'string', 'max:120'],
            'description' => ['required', 'string', 'max:500'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
        ]);

        $this->locationAccess->assertManage($request->user(), (int) $data['location_id']);
        $data['type'] = 'customer_receipt';
        $transaction = $this->ledger->recordTransaction($data, $request->user());

        return response()->json([
            'message' => 'Customer payment received and posted to Customer Advances.',
            'data' => $transaction,
        ], $transaction->wasRecentlyCreated ? 201 : 200);
    }

    public function allocate(Request $request, FinancialTransaction $payment): JsonResponse
    {
        $data = $request->validate([
            'sales_order_id' => ['required', 'integer', 'exists:sales_orders,id'],
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
        ]);

        if ($payment->type !== 'customer_receipt') {
            throw ValidationException::withMessages(['payment' => 'Only customer receipts can be allocated to sales orders.']);
        }

        return DB::transaction(function () use ($request, $payment, $data): JsonResponse {
            $lockedPayment = FinancialTransaction::query()->lockForUpdate()->findOrFail($payment->id);
            $order = SalesOrder::query()->whereKey($data['sales_order_id'])->lockForUpdate()->firstOrFail();

            $this->locationAccess->assertManage($request->user(), (int) $lockedPayment->location_id);
            $this->locationAccess->assertManage($request->user(), (int) $order->location_id);

            if ((int) $lockedPayment->customer_id !== (int) $order->customer_id) {
                throw ValidationException::withMessages(['sales_order_id' => 'The payment customer does not match the sales order customer.']);
            }
            if ((int) $lockedPayment->location_id !== (int) $order->location_id) {
                throw ValidationException::withMessages(['sales_order_id' => 'Payment and sales order must belong to the same location.']);
            }
            if (! in_array($order->status, ['confirmed', 'processing', 'fulfilled', 'completed'], true)) {
                throw ValidationException::withMessages(['sales_order_id' => 'Payments can only be allocated to confirmed or later-stage orders.']);
            }

            $alreadyAllocated = $this->decimal(DB::table('payment_allocations')->where('financial_transaction_id', $lockedPayment->id)->sum('amount'));
            $orderPaid = $this->decimal(DB::table('payment_allocations')->where('sales_order_id', $order->id)->sum('amount'));
            $amount = $this->decimal($data['amount']);
            $paymentRemaining = bcsub((string) $lockedPayment->amount, $alreadyAllocated, 2);
            $orderDue = bcsub((string) $order->grand_total, $orderPaid, 2);

            if (bccomp($amount, $paymentRemaining, 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'Allocation exceeds the unallocated payment balance.']);
            }
            if (bccomp($amount, $orderDue, 2) > 0) {
                throw ValidationException::withMessages(['amount' => 'Allocation exceeds the sales order outstanding balance.']);
            }

            $allocationId = DB::table('payment_allocations')->insertGetId([
                'financial_transaction_id' => $lockedPayment->id,
                'sales_order_id' => $order->id,
                'amount' => $amount,
                'allocated_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return response()->json([
                'message' => 'Payment allocated to sales order.',
                'data' => DB::table('payment_allocations')->where('id', $allocationId)->first(),
                'payment_unallocated' => bcsub($paymentRemaining, $amount, 2),
                'order_due' => bcsub($orderDue, $amount, 2),
            ], 201);
        }, 3);
    }

    public function allocations(Request $request, FinancialTransaction $payment): JsonResponse
    {
        abort_unless($payment->type === 'customer_receipt', 404);
        $this->locationAccess->assertView($request->user(), (int) $payment->location_id);

        $rows = DB::table('payment_allocations as allocations')
            ->join('sales_orders as orders', 'orders.id', '=', 'allocations.sales_order_id')
            ->where('allocations.financial_transaction_id', $payment->id)
            ->orderBy('allocations.id')
            ->get(['allocations.id', 'allocations.amount', 'allocations.created_at', 'orders.id as sales_order_id', 'orders.order_number']);

        return response()->json(['data' => $rows]);
    }


    public function storefrontBankTransfers(Request $request): JsonResponse
    {
        $orders = SalesOrder::query()
            ->with('location:id,name')
            ->where('channel', 'storefront')
            ->where('payment_method', 'bank_transfer')
            ->whereIn('payment_status', ['awaiting_verification', 'approved'])
            ->when($request->filled('location_id'), function ($query) use ($request): void {
                $id = $request->integer('location_id');
                $this->locationAccess->assertView($request->user(), $id);
                $query->where('location_id', $id);
            }, fn ($query) => $this->locationAccess->scopeViewable($query, $request->user()))
            ->latest('id')->get();

        return response()->json(['data' => $orders->map(fn (SalesOrder $order): array => [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'grand_total' => (string) $order->grand_total,
            'payment_status' => $order->payment_status,
            'payment_details' => $order->payment_details ?? [],
            'location' => $order->location?->name,
            'created_at' => $order->created_at?->toDateTimeString(),
        ])]);
    }

    public function approveStorefrontBankTransfer(Request $request, SalesOrder $order): JsonResponse
    {
        $this->locationAccess->assertManage($request->user(), (int) $order->location_id);

        return DB::transaction(function () use ($request, $order): JsonResponse {
            $locked = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->channel !== 'storefront' || $locked->payment_method !== 'bank_transfer') {
                throw ValidationException::withMessages(['order' => 'This is not a storefront bank-transfer order.']);
            }
            if ($locked->payment_status !== 'awaiting_verification') {
                throw ValidationException::withMessages(['payment_status' => 'Only submissions awaiting verification can be approved.']);
            }
            $details = $locked->payment_details ?? [];
            $declared = (string) ($details['declared_amount'] ?? '0');
            if (bccomp($declared, (string) $locked->grand_total, 2) !== 0) {
                throw ValidationException::withMessages(['amount' => 'The customer-declared transfer amount does not match the order total. Verify the bank statement before approval.']);
            }
            $locked->payment_status = 'approved';
            $locked->payment_details = array_merge($details, [
                'approved_at' => now()->toIso8601String(),
                'approved_by' => $request->user()->id,
            ]);
            $locked->save();

            return response()->json(['message' => 'Bank transfer approved. Mark it as received only after the funds are visible in the bank account.', 'data' => $locked]);
        }, 3);
    }

    public function receiveStorefrontBankTransfer(Request $request, SalesOrder $order): JsonResponse
    {
        $data = $request->validate([
            'cash_account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'transaction_date' => ['required', 'date'],
        ]);
        $this->locationAccess->assertManage($request->user(), (int) $order->location_id);

        return DB::transaction(function () use ($request, $order, $data): JsonResponse {
            $locked = SalesOrder::query()->lockForUpdate()->findOrFail($order->id);
            if ($locked->channel !== 'storefront' || $locked->payment_method !== 'bank_transfer' || $locked->payment_status !== 'approved') {
                throw ValidationException::withMessages(['payment_status' => 'Approve the bank transfer before marking it received.']);
            }
            if ($locked->status !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Only pending storefront orders can be confirmed through this payment review.']);
            }
            $details = $locked->payment_details ?? [];
            $assessment = $locked->riskAssessments()->latest('id')->first();
            if ($assessment && in_array($assessment->decision, ['manual_review', 'reject'], true)) {
                throw ValidationException::withMessages(['risk' => 'This order requires an approved risk review before confirmation.']);
            }
            if ($assessment && $assessment->decision === 'verify' && ! \App\Models\SalesOrderPhoneVerification::query()->where('sales_order_id', $locked->id)->whereNotNull('verified_at')->exists()) {
                throw ValidationException::withMessages(['risk' => 'Verify the customer phone before confirming this order.']);
            }

            $receipt = $this->ledger->recordTransaction([
                'idempotency_key' => 'ST-BANK-'.$locked->order_number,
                'transaction_date' => $data['transaction_date'],
                'amount' => (string) $locked->grand_total,
                'payment_method' => 'bank',
                'cash_account_id' => (int) $data['cash_account_id'],
                'customer_id' => (int) $locked->customer_id,
                'reference' => (string) ($details['transaction_reference'] ?? $locked->order_number),
                'description' => 'Verified storefront bank transfer for '.$locked->order_number,
                'location_id' => (int) $locked->location_id,
                'type' => 'customer_receipt',
            ], $request->user());

            DB::table('payment_allocations')->insertOrIgnore([
                'financial_transaction_id' => $receipt->id,
                'sales_order_id' => $locked->id,
                'amount' => (string) $locked->grand_total,
                'allocated_by' => $request->user()->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $locked->payment_status = 'received';
            $locked->payment_details = array_merge($details, [
                'received_at' => now()->toIso8601String(),
                'received_by' => $request->user()->id,
                'financial_transaction_id' => $receipt->id,
            ]);
            $locked->save();

            $confirmed = $this->orders->confirm($locked, $request->user());

            return response()->json([
                'message' => 'Payment received, allocated to the order, and the order is confirmed.',
                'data' => ['order' => $confirmed, 'payment' => $receipt],
            ]);
        }, 3);
    }

    /**
     * @return numeric-string
     */
    private function decimal(mixed $value): string
    {
        /** @var numeric-string $normalized */
        $normalized = (string) ($value ?? '0.00');

        return bcadd($normalized, '0', 2);
    }
}
