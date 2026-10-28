<?php

namespace App\Http\Controllers;

use App\Models\Lead;
use App\Models\Location;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class LeadController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Lead::query()->with(['assignee:id,name,designation', 'creator:id,name'])->withCount('activities')->search($request->string('search')->toString());
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

    /**
     * Return only active staff in locations the signed-in user belongs to.
     * The server re-checks every assignment; this endpoint is only for UI choices.
     */
    public function assignmentOptions(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $user = $request->user();
        $isDeveloper = $authorization->isDeveloper($user);
        $canAssignAcrossLocations = $user->can('system.crm.leads.assign');

        $locations = $isDeveloper
            ? Location::query()->active()->orderBy('name')->get(['id', 'name'])
            : $user->locations()->where('locations.is_active', true)->orderBy('locations.name')->get(['locations.id', 'locations.name']);

        if ($canAssignAcrossLocations && $locations->isEmpty()) {
            $locations = Location::query()->active()->orderBy('name')->get(['id', 'name']);
        }

        $members = $locations->isEmpty() ? collect() : User::query()
            ->where('status', 'active')
            ->whereHas('locations', fn ($q) => $q->whereIn('locations.id', $locations->pluck('id')))
            ->with(['locations' => fn ($q) => $q->whereIn('locations.id', $locations->pluck('id'))->select('locations.id', 'locations.name')])
            ->orderBy('name')
            ->get(['id', 'name', 'designation'])
            ->map(fn (User $member) => [
                'id' => $member->id,
                'name' => $member->name,
                'designation' => $member->designation,
                'location_ids' => $member->locations->pluck('id')->map(fn ($id) => (int) $id)->values(),
            ])->values();

        return response()->json([
            'locations' => $locations->map(fn ($location) => ['id' => $location->id, 'name' => $location->name])->values(),
            'members' => $members,
            'can_manage_assignments' => $isDeveloper || $canAssignAcrossLocations || $this->isManagerOrSupervisor($user),
            'current_user_id' => $user->id,
        ]);
    }

    public function show(Lead $lead): JsonResponse
    {
        $lead->load([
            'assignee:id,name,designation', 'creator:id,name', 'customer:id,customer_code,name,phone,company_name',
            'convertedOrder:id,order_number,status,grand_total',
            'activities.creator:id,name',
            'assignmentHistory.fromUser:id,name', 'assignmentHistory.toUser:id,name', 'assignmentHistory.actor:id,name',
        ])->loadCount('activities');
        return response()->json(['data' => $lead]);
    }

    public function store(Request $request, AuthorizationService $authorization): JsonResponse
    {
        $data = $this->validated($request);
        $requestedAssignee = $request->input('assigned_to');
        $requestedLocation = $request->validate(['location_id' => ['nullable', 'integer', 'exists:locations,id']])['location_id'] ?? null;
        $lead = DB::transaction(function () use ($data, $request, $requestedAssignee, $requestedLocation, $authorization): Lead {
            $data['lead_code'] = $this->newCode();
            $data['created_by'] = $request->user()?->id;
            $data['updated_by'] = $request->user()?->id;
            $data['location_id'] = $requestedLocation ? (int) $requestedLocation : $this->defaultLocationId($request->user(), $authorization);
            if ($data['location_id'] && ! $authorization->isDeveloper($request->user()) && ! $request->user()->locations()->where('locations.id', $data['location_id'])->where('locations.is_active', true)->exists()) {
                abort(403, 'You cannot create leads for a location you are not assigned to.');
            }
            if ($requestedAssignee !== null && $requestedAssignee !== '') {
                $assignee = User::query()->where('status', 'active')->findOrFail($requestedAssignee);
                if ((int) $assignee->id === (int) $request->user()->id && ! $authorization->isDeveloper($request->user()) && ! $request->user()->can('system.crm.leads.assign') && ! $this->isManagerOrSupervisor($request->user())) {
                    throw ValidationException::withMessages(['assigned_to' => 'You cannot assign a lead to yourself. Ask a manager to assign it to you.']);
                }
                $locationId = $this->resolveAssignmentLocation($request->user(), $assignee, $data['location_id'], $authorization, true);
                $data['location_id'] = $locationId;
                $data['assigned_to'] = $assignee->id;
            }
            $created = Lead::create($data);
            if (! empty($data['assigned_to'])) {
                $created->assignmentHistory()->create([
                    'from_user_id' => null,
                    'to_user_id' => $data['assigned_to'],
                    'assigned_by' => $request->user()?->id,
                    'reason' => 'Initial assignment on lead creation',
                ]);
            }
            return $created;
        });
        return response()->json(['message' => 'Lead created successfully.', 'data' => $lead->load('assignee:id,name')], 201);
    }

    public function update(Request $request, Lead $lead): JsonResponse
    {
        $data = $this->validated($request, $lead);
        // Owner changes must use the dedicated assignment endpoint so policy and audit history cannot be bypassed.
        unset($data['assigned_to']);
        $data['updated_by'] = $request->user()?->id;
        $lead->update($data);
        return response()->json(['message' => 'Lead updated successfully.', 'data' => $lead->fresh(['assignee:id,name'])]);
    }

    public function assign(Request $request, Lead $lead, AuthorizationService $authorization): JsonResponse
    {
        $data = $request->validate([
            'assigned_to' => ['required', 'integer', 'exists:users,id'],
            'reason' => ['nullable', 'string', 'max:500'],
            'location_id' => ['nullable', 'integer', 'exists:locations,id'],
        ]);
        $actor = $request->user();
        $newOwner = User::query()->where('status', 'active')->findOrFail($data['assigned_to']);
        $oldOwnerId = $lead->assigned_to;
        $isDeveloper = $authorization->isDeveloper($actor);
        $isManager = $this->isManagerOrSupervisor($actor) || $actor->can('system.crm.leads.assign');

        if ((int) $newOwner->id === (int) $actor->id && ! $isDeveloper && ! $isManager) {
            throw ValidationException::withMessages(['assigned_to' => 'You cannot claim a lead for yourself. Ask a manager to assign it to you.']);
        }
        if ($oldOwnerId === $newOwner->id) {
            throw ValidationException::withMessages(['assigned_to' => 'This lead is already assigned to that team member.']);
        }

        $locationId = $lead->location_id ?: ($data['location_id'] ?? null);
        if (! $locationId) {
            $sharedLocationIds = $actor->locations()->whereHas('users', fn ($q) => $q->where('users.id', $newOwner->id))->pluck('locations.id');
            if ($sharedLocationIds->count() === 1) $locationId = (int) $sharedLocationIds->first();
        }
        if (! $locationId) {
            throw ValidationException::withMessages(['location_id' => 'Choose a team location before assigning this lead.']);
        }

        $newOwnerBelongs = $newOwner->locations()->where('locations.id', $locationId)->where('locations.is_active', true)->exists();
        if (! $newOwnerBelongs) {
            throw ValidationException::withMessages(['assigned_to' => 'The selected owner is not an active member of this location.']);
        }

        $actorBelongs = $actor->locations()->where('locations.id', $locationId)->where('locations.is_active', true)->exists();
        $isCurrentOwner = (int) $oldOwnerId === (int) $actor->id;
        $isLeadCreator = (int) $lead->created_by === (int) $actor->id && $actor->can('system.crm.leads.create');
        $isCreatorInitialAssign = $oldOwnerId === null && $isLeadCreator;

        if (! $isDeveloper && ! ($isManager && ($actorBelongs || $actor->can('system.crm.leads.assign'))) && ! ($isCurrentOwner && $actorBelongs) && ! ($isLeadCreator && $actorBelongs)) {
            abort(403, 'You are not allowed to assign or transfer this lead.');
        }

        if (! $isDeveloper && ! $isManager && $oldOwnerId !== null && ! $isCurrentOwner && ! $isLeadCreator) {
            abort(403, 'Only the current owner or a location manager can transfer this lead.');
        }

        DB::transaction(function () use ($lead, $data, $locationId, $actor, $oldOwnerId): void {
            $lead->update([
                'assigned_to' => $data['assigned_to'],
                'location_id' => $locationId,
                'updated_by' => $actor->id,
            ]);
            $lead->assignmentHistory()->create([
                'from_user_id' => $oldOwnerId,
                'to_user_id' => $data['assigned_to'],
                'assigned_by' => $actor->id,
                'reason' => trim((string) ($data['reason'] ?? '')) ?: null,
            ]);
        });

        return response()->json([
            'message' => 'Lead assigned successfully.',
            'data' => $lead->fresh(['assignee:id,name,designation', 'assignmentHistory.fromUser:id,name', 'assignmentHistory.toUser:id,name', 'assignmentHistory.actor:id,name']),
        ]);
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
            'phone' => ['required', 'string', 'max:40', 'regex:/^[0-9+()\\- .]{7,40}$/'],
            'alternate_phone' => ['nullable', 'string', 'max:40', 'regex:/^[0-9+()\\- .]{7,40}$/'],
            'email' => ['nullable', 'email', 'max:190'],
            'address_line' => ['nullable', 'string', 'max:500'],
            'area' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'division' => ['nullable', 'string', 'max:120'],
            'source' => ['required', Rule::in(['manual', 'facebook', 'website', 'whatsapp', 'referral', 'walk_in', 'phone', 'other'])],
            'source_detail' => ['nullable', 'string', 'max:190'],
            'customer_type' => ['required', Rule::in(['retail', 'b2b', 'undecided'])],
            'interested_product' => ['nullable', 'string', 'max:190'],
            'status' => ['required', Rule::in($lead?->status === 'converted' ? $this->statuses() : array_values(array_diff($this->statuses(), ['converted'])) )],
            'last_contact_outcome' => ['nullable', Rule::in(['answered', 'no_answer', 'busy', 'callback_requested', 'interested', 'not_interested', 'wrong_number', 'message_sent', 'other'])],
            'interest_level' => ['required', Rule::in(['hot', 'warm', 'cold'])],
            'notes' => ['nullable', 'string', 'max:10000'],
            'next_follow_up_at' => ['nullable', 'date'],
        ]);
    }

    private function defaultLocationId(User $user, AuthorizationService $authorization): ?int
    {
        if ($authorization->isDeveloper($user)) return null;
        $ids = $user->locations()->where('locations.is_active', true)->pluck('locations.id');
        return $ids->count() === 1 ? (int) $ids->first() : null;
    }

    private function resolveAssignmentLocation(User $actor, User $assignee, ?int $currentLocationId, AuthorizationService $authorization, bool $initial): int
    {
        $locationId = $currentLocationId;
        if ($locationId && $assignee->locations()->where('locations.id', $locationId)->where('locations.is_active', true)->exists()) {
            if ($authorization->isDeveloper($actor) || $actor->can('system.crm.leads.assign') || ($actor->locations()->where('locations.id', $locationId)->exists() && ($initial || $this->isManagerOrSupervisor($actor)))) {
                return $locationId;
            }
        }
        $shared = $actor->locations()->whereHas('users', fn ($q) => $q->where('users.id', $assignee->id))->where('locations.is_active', true)->pluck('locations.id');
        if ($shared->count() === 1 && ($initial || $this->isManagerOrSupervisor($actor) || $actor->can('system.crm.leads.assign'))) {
            return (int) $shared->first();
        }
        throw ValidationException::withMessages(['assigned_to' => 'You can assign leads only to a team member in an authorized location.']);
    }

    private function isManagerOrSupervisor(User $user): bool
    {
        $designation = Str::lower(trim((string) $user->designation));
        return in_array($designation, ['manager', 'supervisor', 'sales manager', 'sales supervisor', 'branch manager', 'team leader'], true);
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
