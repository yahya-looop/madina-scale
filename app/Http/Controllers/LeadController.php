<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LeadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Lead::query()->with(['assignee:id,name', 'creator:id,name'])->withCount('activities')->search($request->string('search')->toString());
        if ($request->filled('status') && $request->input('status') !== 'all') {
            $query->where('status', $request->validate(['status' => ['required', Rule::in($this->statuses())]])['status']);
        }
        if ($request->filled('customer_type') && $request->input('customer_type') !== 'all') {
            $query->where('customer_type', $request->validate(['customer_type' => ['required', Rule::in(['retail', 'b2b', 'undecided'])]])['customer_type']);
        }
        if ($request->filled('follow_up') && $request->input('follow_up') === 'due') {
            $query->whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now())->whereNotIn('status', ['converted', 'lost']);
        }

        $stats = [
            'total' => Lead::count(),
            'new' => Lead::where('status', 'new')->count(),
            'follow_up_due' => Lead::whereNotNull('next_follow_up_at')->where('next_follow_up_at', '<=', now())->whereNotIn('status', ['converted', 'lost'])->count(),
            'converted' => Lead::where('status', 'converted')->count(),
        ];
        $sortBy = $request->input('sort_by', 'follow_up');
        $sortDirection = $request->input('sort_direction', 'asc');
        if (! in_array($sortBy, ['follow_up', 'created_at', 'name', 'status', 'interest_level'], true)) $sortBy = 'follow_up';
        if (! in_array($sortDirection, ['asc', 'desc'], true)) $sortDirection = 'asc';

        if ($sortBy === 'follow_up') {
            $query->orderByRaw('CASE WHEN next_follow_up_at IS NULL THEN 1 ELSE 0 END')
                ->orderBy('next_follow_up_at', $sortDirection);
        } else {
            $query->orderBy($sortBy, $sortDirection);
        }
        $leads = $query->orderByDesc('id')
            ->paginate(min(max($request->integer('per_page', 15), 1), 50))->withQueryString();

        return response()->json(array_merge($leads->toArray(), ['stats' => $stats]));
    }

    public function show(Lead $lead): JsonResponse
    {
        $lead->load([
            'assignee:id,name', 'creator:id,name', 'customer:id,customer_code,name,phone,company_name',
            'convertedOrder:id,order_number,status,grand_total',
            'activities.creator:id,name',
        ])->loadCount('activities');
        return response()->json(['data' => $lead]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $lead = DB::transaction(function () use ($data, $request): Lead {
            $data['lead_code'] = $this->newCode();
            $data['created_by'] = $request->user()?->id;
            $data['updated_by'] = $request->user()?->id;
            return Lead::create($data);
        });
        return response()->json(['message' => 'Lead created successfully.', 'data' => $lead], 201);
    }

    public function update(Request $request, Lead $lead): JsonResponse
    {
        $data = $this->validated($request, $lead);
        $data['updated_by'] = $request->user()?->id;
        $lead->update($data);
        return response()->json(['message' => 'Lead updated successfully.', 'data' => $lead->fresh(['assignee:id,name'])]);
    }

    public function destroy(Lead $lead): JsonResponse
    {
        if ($lead->status === 'converted' || $lead->customer_id !== null || $lead->converted_order_id !== null) {
            return response()->json(['message' => 'Converted leads are retained for sales attribution; mark the lead as lost instead.'], 422);
        }
        $lead->delete();
        return response()->json(['message' => 'Lead archived successfully.']);
    }

    public function storeActivity(Request $request, Lead $lead): JsonResponse
    {
        $data = $request->validate([
            'activity_type' => ['required', Rule::in(['call', 'note', 'whatsapp', 'email', 'meeting', 'other'])],
            'outcome' => ['nullable', Rule::in(['answered', 'no_answer', 'busy', 'callback_requested', 'interested', 'not_interested', 'wrong_number', 'message_sent', 'other'])],
            'summary' => ['required', 'string', 'max:5000'],
            'occurred_at' => ['required', 'date'],
            'duration_seconds' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'next_follow_up_at' => ['nullable', 'date', 'after_or_equal:occurred_at'],
        ]);
        $activity = DB::transaction(function () use ($data, $lead, $request) {
            $data['created_by'] = $request->user()?->id;
            $activity = $lead->activities()->create($data);
            $leadUpdates = ['last_contact_outcome' => $data['outcome'] ?? null, 'updated_by' => $request->user()?->id];
            if (array_key_exists('next_follow_up_at', $data)) $leadUpdates['next_follow_up_at'] = $data['next_follow_up_at'];
            if (($data['outcome'] ?? null) === 'not_interested') $leadUpdates['status'] = 'lost';
            elseif (in_array(($data['outcome'] ?? null), ['answered', 'interested', 'callback_requested'], true) && $lead->status === 'new') $leadUpdates['status'] = 'contacting';
            $lead->update($leadUpdates);
            return $activity->load('creator:id,name');
        });
        return response()->json(['message' => 'Activity recorded successfully.', 'data' => $activity], 201);
    }

    private function validated(Request $request, ?Lead $lead = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+()\- .]{7,40}$/'],
            'alternate_phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\- .]{7,40}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'address_line' => ['nullable', 'string', 'max:500'],
            'area' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'division' => ['nullable', 'string', 'max:120'],
            'source' => ['required', Rule::in(['manual', 'facebook', 'website', 'whatsapp', 'referral', 'walk_in', 'phone', 'other'])],
            'source_detail' => ['nullable', 'string', 'max:190'],
            'customer_type' => ['required', Rule::in(['retail', 'b2b', 'undecided'])],
            'interested_product' => ['nullable', 'string', 'max:190'],
            'status' => ['required', Rule::in($lead?->status === 'converted' ? $this->statuses() : array_values(array_diff($this->statuses(), ['converted'])))],
            'last_contact_outcome' => ['nullable', Rule::in(['answered', 'no_answer', 'busy', 'callback_requested', 'interested', 'not_interested', 'wrong_number', 'message_sent', 'other'])],
            'interest_level' => ['required', Rule::in(['hot', 'warm', 'cold'])],
            'notes' => ['nullable', 'string', 'max:10000'],
            'next_follow_up_at' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);
    }

    private function statuses(): array
    {
        return ['new', 'contacting', 'qualified', 'interested', 'follow_up', 'converted', 'lost'];
    }

    private function newCode(): string
    {
        do { $code = 'LEAD-'.now()->format('ym').'-'.Str::upper(Str::random(6)); }
        while (Lead::where('lead_code', $code)->exists());
        return $code;
    }
}
