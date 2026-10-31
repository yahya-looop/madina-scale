<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use App\Services\AuthorizationService;
use App\Services\ActivityLogService;
use App\Models\Location;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
        private readonly AuthorizationService $authorization,
        private readonly ActivityLogService $activityLog,
    ) {}

    /** Return active locations available for assignment. */
    public function locationOptions(): JsonResponse
    {
        abort_unless(auth()->user() && $this->authorization->can(auth()->user(), 'system.locations.manage'), 403);

        return response()->json([
            'data' => Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'code', 'type']),
        ]);
    }

    /**
     * Display a listing of users available for management.
     */
    public function index(): JsonResponse
    {
        $users = $this->userService->list();

        return response()->json([
            'data' => $users
                ->map(fn (User $user): array => $this->userPayload($user))
                ->values()
                ->all(),
        ]);
    }

    /**
     * Store a newly created user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        if (array_key_exists('location_assignments', $validated)) {
            abort_unless($this->authorization->can($request->user(), 'system.locations.manage'), 403, 'Location management permission is required.');
        }

        return DB::transaction(function () use ($request, $validated): JsonResponse {
            $user = $this->userService->create($validated);

            // Profile, roles, and location assignments must commit or roll back together.
            if (array_key_exists('role_ids', $validated)) {
                $user = $this->userService->syncRoles(
                    $user,
                    $validated['role_ids'] ?? [],
                );
            }

            if (array_key_exists('location_assignments', $validated)) {
                $user = $this->userService->syncLocationAssignments(
                    $user,
                    $validated['location_assignments'] ?? [],
                    (int) $request->user()->id,
                );
            }

            $this->activityLog->recordForUser($request->user(), 'create', 'users', 'User account created.', $user, null, ['name' => $user->name, 'email' => $user->email, 'status' => $user->status, 'role_ids' => $user->roles->pluck('id')->all()]);

            return response()->json([
                'message' => 'User created successfully.',
                'data' => $this->userPayload($user),
            ], 201);
        });
    }

    /**
     * Display the specified user.
     */
    public function show(User $user): JsonResponse
    {
        $user = $this->userService->find($user->id);

        return response()->json([
            'data' => $this->userPayload($user),
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(
        UpdateUserRequest $request,
        User $user,
    ): JsonResponse {
        $validated = $request->validated();

        if (array_key_exists('location_assignments', $validated)) {
            abort_unless($this->authorization->can($request->user(), 'system.locations.manage'), 403, 'Location management permission is required.');
        }

        return DB::transaction(function () use ($request, $user, $validated): JsonResponse {
            // Profile, roles, and location assignments must commit or roll back together.
            $user = $this->userService->update(
                $user,
                $validated,
            );

            if (array_key_exists('role_ids', $validated)) {
                $user = $this->userService->syncRoles(
                    $user,
                    $validated['role_ids'] ?? [],
                );
            }

            if (array_key_exists('location_assignments', $validated)) {
                $user = $this->userService->syncLocationAssignments(
                    $user,
                    $validated['location_assignments'] ?? [],
                    (int) $request->user()->id,
                );
            }

            $this->activityLog->recordForUser($request->user(), 'update', 'users', 'User account updated.', $user, null, ['name' => $user->name, 'email' => $user->email, 'status' => $user->status, 'role_ids' => $user->roles->pluck('id')->all()]);

            return response()->json([
                'message' => 'User updated successfully.',
                'data' => $this->userPayload($user),
            ]);
        });
    }

    /**
     * Build the public payload used by user-management screens.
     *
     * Authentication secrets such as password and remember_token
     * are intentionally excluded.
     *
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        $user->loadMissing([
            'roles:id,name,slug,is_system,is_active',
            'locations:id,name,code,type,is_active',
        ]);

        return [
            /*
             * Identity
             */
            'id' => $user->id,
            'name' => $user->name,
            'designation' => $user->designation,
            'email' => $user->email,

            /*
             * Account status
             */
            'status' => $user->status,

            /*
             * Login tracking
             *
             * These fields are consumed by Users/Index.vue
             * for the Last Login column.
             */
            'last_login_at' => $user->last_login_at?->toISOString(),
            'last_login_ip' => $user->last_login_ip,

            /*
             * Account security
             */
            'failed_login_attempts' => $user->failed_login_attempts,
            'locked_until' => $user->locked_until?->toISOString(),
            'password_changed_at' => $user->password_changed_at?->toISOString(),

            /*
             * Verification
             */
            'email_verified_at' => $user->email_verified_at?->toISOString(),

            /*
             * Record metadata
             */
            'created_at' => $user->created_at?->toISOString(),
            'updated_at' => $user->updated_at?->toISOString(),

            /*
             * Roles
             */
            'locations' => $user->locations->map(static fn ($location): array => [
                'id' => $location->id,
                'name' => $location->name,
                'code' => $location->code,
                'type' => $location->type,
                'access_level' => $location->pivot->access_level,
            ])->values()->all(),
            'roles' => $user->roles
                ->map(
                    static fn ($role): array => [
                        'id' => $role->id,
                        'name' => $role->name,
                        'slug' => $role->slug,
                        'isSystem' => (bool) $role->is_system,
                        'isActive' => (bool) $role->is_active,
                    ],
                )
                ->values()
                ->all(),
        ];
    }
}

