<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Customer::query()->withCount('orders')->search($request->string('search')->toString());
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->validate(['status' => ['required', Rule::in(['active', 'inactive'])]])['status']);
        }
        $stats = [
            'total' => Customer::count(),
            'active' => Customer::where('status', 'active')->count(),
            'with_phone' => Customer::whereNotNull('phone')->where('phone', '<>', '')->count(),
        ];
        $customers = $query->orderByDesc('id')->paginate(min(max($request->integer('per_page', 15), 1), 50))->withQueryString();
        return response()->json(array_merge($customers->toArray(), ['stats' => $stats]));
    }

    public function options(Request $request): JsonResponse
    {
        $term = trim($request->string('search')->toString());
        $customers = Customer::query()
            ->where('status', 'active')
            ->search($term)
            ->orderBy('name')
            ->limit(10)
            ->get(['id', 'customer_code', 'name', 'phone', 'email', 'company_name', 'status']);

        return response()->json(['data' => $customers]);
    }

    public function show(Customer $customer): JsonResponse
    {
        $customer->load(['orders' => fn ($q) => $q->select('id', 'customer_id', 'order_number', 'status', 'grand_total', 'currency', 'created_at')->latest()->limit(25)]);
        $customer->loadCount('orders');
        $customer->setAttribute('lifetime_value', (string) $customer->orders()->whereNotIn('status', ['cancelled'])->sum('grand_total'));
        return response()->json(['data' => $customer]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['customer_code'] = $this->newCode();
        $customer = Customer::create($data);
        return response()->json(['message' => 'Customer created successfully.', 'data' => $customer], 201);
    }

    public function update(Request $request, Customer $customer): JsonResponse
    {
        $customer->update($this->validated($request, $customer));
        return response()->json(['message' => 'Customer updated successfully.', 'data' => $customer->fresh()]);
    }

    public function destroy(Customer $customer): JsonResponse
    {
        if ($customer->orders()->exists()) {
            throw ValidationException::withMessages(['customer' => 'This customer has order history and cannot be deleted. Mark the customer inactive instead.']);
        }
        $customer->delete();
        return response()->json(['message' => 'Customer deleted successfully.']);
    }

    private function validated(Request $request, ?Customer $customer = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\- .]{7,40}$/', Rule::unique('customers', 'phone')->ignore($customer?->id)->where(fn ($q) => $q->whereNotNull('phone'))],
            'email' => ['nullable', 'email', 'max:190', Rule::unique('customers', 'email')->ignore($customer?->id)->where(fn ($q) => $q->whereNotNull('email'))],
            'company_name' => ['nullable', 'string', 'max:160'],
            'address_line' => ['nullable', 'string', 'max:500'],
            'area' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'division' => ['nullable', 'string', 'max:120'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);
    }

    private function newCode(): string
    {
        do { $code = 'CUS-' . now()->format('ym') . '-' . Str::upper(Str::random(6)); }
        while (Customer::where('customer_code', $code)->exists());
        return $code;
    }
}
