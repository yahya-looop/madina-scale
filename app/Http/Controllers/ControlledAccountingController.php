<?php

namespace App\Http\Controllers;

use App\Models\ControlledAccountingEntry;
use App\Services\ControlledAccountingService;
use App\Services\LocationInventoryAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ControlledAccountingController extends Controller
{
    public function __construct(
        private readonly ControlledAccountingService $workflow,
        private readonly LocationInventoryAccessService $locationAccess,
    ) {}

    public function openingBalances(Request $request): JsonResponse
    {
        $query = ControlledAccountingEntry::query()
            ->where('workflow_type', 'opening_balance')
            ->with(['journal.lines.account', 'location:id,name,code', 'creator:id,name', 'approver:id,name', 'poster:id,name'])
            ->latest('id');

        $this->locationAccess->scopeViewable($query, $request->user(), 'location_id');

        return response()->json(['data' => $query->paginate(min(max($request->integer('per_page', 20), 1), 100))]);
    }

    public function companyAdjustments(Request $request): JsonResponse
    {
        $entries = ControlledAccountingEntry::query()
            ->where('workflow_type', 'company_adjustment')
            ->whereNull('location_id')
            ->with(['journal.lines.account', 'creator:id,name', 'approver:id,name', 'poster:id,name'])
            ->latest('id')
            ->paginate(min(max($request->integer('per_page', 20), 1), 100));

        return response()->json(['data' => $entries]);
    }

    public function storeOpeningBalance(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'fiscal_year' => ['required', 'integer', 'between:2000,2100'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'description' => ['required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:120'],
            'lines' => ['required', 'array', 'min:2', 'max:100'],
            'lines.*.account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'lines.*.debit' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,2})?$/'],
            'lines.*.credit' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,2})?$/'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = $this->workflow->create('opening_balance', $data, $request->user());

        return response()->json(['data' => $entry], 201);
    }

    public function storeCompanyAdjustment(Request $request): JsonResponse
    {
        $data = $request->validate([
            'entry_date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:500'],
            'reference' => ['nullable', 'string', 'max:120'],
            'lines' => ['required', 'array', 'min:2', 'max:100'],
            'lines.*.account_id' => ['required', 'integer', 'exists:financial_accounts,id'],
            'lines.*.debit' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,2})?$/'],
            'lines.*.credit' => ['required', 'regex:/^\d{1,14}(?:\.\d{1,2})?$/'],
            'lines.*.description' => ['nullable', 'string', 'max:255'],
        ]);

        $entry = $this->workflow->create('company_adjustment', $data, $request->user());

        return response()->json(['data' => $entry], 201);
    }

    public function approve(Request $request, ControlledAccountingEntry $entry): JsonResponse
    {
        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);
        $updated = $this->workflow->approve($entry, $request->user(), $data['note'] ?? null);

        return response()->json(['data' => $updated]);
    }

    public function post(Request $request, ControlledAccountingEntry $entry): JsonResponse
    {
        $updated = $this->workflow->post($entry, $request->user());

        return response()->json(['data' => $updated]);
    }

    public function reject(Request $request, ControlledAccountingEntry $entry): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'min:5', 'max:2000']]);
        $updated = $this->workflow->reject($entry, $request->user(), $data['note']);

        return response()->json(['data' => $updated]);
    }
}
