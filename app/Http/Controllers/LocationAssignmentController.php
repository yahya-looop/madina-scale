<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\User;
use App\Services\AuthorizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LocationAssignmentController extends Controller
{
    public function __construct(private readonly AuthorizationService $authorization) {}

    public function index(Request $request, Location $location): JsonResponse
    {
        $this->authorizeManager($request);

        $assigned = $location->users()->orderBy('name')->get(['users.id', 'users.name', 'users.email', 'users.status'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'access_level' => $user->pivot->access_level,
            ])->values();

        // Only active users with the inventory-transfer permission are eligible
        // to be assigned as location inventory operators.
        $candidates = User::query()->where('status', 'active')
            ->whereHas('roles.permissions', fn ($query) => $query->where('slug', 'system.inventory.transfer'))
            ->orderBy('name')->get(['id', 'name', 'email', 'status'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
            ])->values();

        return response()->json(['data' => $assigned, 'candidates' => $candidates]);
    }

    public function sync(Request $request, Location $location): JsonResponse
    {
        $this->authorizeManager($request);
        $data = $request->validate([
            'assignments' => ['sometimes', 'array', 'max:500'],
            'assignments.*.user_id' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'assignments.*.access_level' => ['required', 'in:view,receive,manage'],
            // Backward-compatible bulk assignment payload.
            'user_ids' => ['sometimes', 'array', 'max:500'],
            'user_ids.*' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'access_level' => ['nullable', 'in:view,receive,manage'],
        ]);

        if (! array_key_exists('assignments', $data) && ! array_key_exists('user_ids', $data)) {
            abort(422, 'Provide assignments or user_ids.');
        }

        $actorId = (int) $request->user()->id;
        DB::transaction(function () use ($location, $data, $actorId): void {
            if (array_key_exists('assignments', $data)) {
                $assignmentRows = collect($data['assignments'])->keyBy(fn (array $row) => (int) $row['user_id']);
                $eligibleIds = User::query()->where('status', 'active')
                    ->whereHas('roles.permissions', fn ($query) => $query->where('slug', 'system.inventory.transfer'))
                    ->whereIn('id', $assignmentRows->keys())->pluck('id')->map(fn ($id) => (int) $id)->all();
                if (count($eligibleIds) !== $assignmentRows->count()) {
                    throw ValidationException::withMessages([
                        'assignments' => 'Only active users with inventory-transfer permission can be assigned.',
                    ]);
                }
                $sync = [];
                foreach ($assignmentRows as $userId => $row) {
                    $sync[(int) $userId] = [
                        'access_level' => $row['access_level'],
                        'assigned_by' => $actorId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                $location->users()->sync($sync);
            } else {
                $level = $data['access_level'] ?? 'manage';
                $eligibleIds = User::query()->where('status', 'active')
                    ->whereHas('roles.permissions', fn ($query) => $query->where('slug', 'system.inventory.transfer'))
                    ->whereIn('id', $data['user_ids'])->pluck('id')->map(fn ($id) => (int) $id)->all();
                if (count($eligibleIds) !== count($data['user_ids'])) {
                    throw ValidationException::withMessages([
                        'user_ids' => 'Only active users with inventory-transfer permission can be assigned.',
                    ]);
                }
                $sync = [];
                foreach ($data['user_ids'] as $userId) {
                    $sync[(int) $userId] = [
                        'access_level' => $level,
                        'assigned_by' => $actorId,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
                $location->users()->sync($sync);
            }
        });

        $assigned = $location->users()->orderBy('name')->get(['users.id', 'users.name', 'users.email', 'users.status'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'status' => $user->status,
                'access_level' => $user->pivot->access_level,
            ])->values();
        $candidates = User::query()->where('status', 'active')
            ->whereHas('roles.permissions', fn ($query) => $query->where('slug', 'system.inventory.transfer'))
            ->orderBy('name')->get(['id', 'name', 'email', 'status'])
            ->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'status' => $user->status])
            ->values();

        return response()->json([
            'message' => 'Location access assignments updated.',
            'data' => $assigned,
            'candidates' => $candidates,
        ]);
    }

    private function authorizeManager(Request $request): void
    {
        abort_unless($request->user() !== null, 401);
        abort_unless($this->authorization->can($request->user(), 'system.locations.manage'), 403);
    }
}
