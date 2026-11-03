<?php

namespace App\Http\Controllers;

use App\Http\Requests\Role\StoreRoleRequest;
use App\Http\Requests\Role\SyncRolePermissionsRequest;
use App\Http\Requests\Role\UpdateRoleRequest;
use App\Models\Role;
use App\Services\ActivityLogService;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class RoleController extends Controller
{
    public function __construct(
        protected RoleService $roles,
        protected ActivityLogService $activityLog,
    ) {}

    /**
     * Display all roles available for management.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->roles->list(),
        ]);
    }

    /**
     * Display a single role.
     */
    public function show(Role $role): JsonResponse
    {
        $role->load('permissions');

        return response()->json([
            'data' => $role,
        ]);
    }

    /**
     * Store a newly created role.
     */
    public function store(StoreRoleRequest $request): JsonResponse
    {
        /** @var array{
         *     name: string,
         *     slug: string,
         *     description?: string|null,
         *     is_active?: bool
         * } $attributes
         */
        $attributes = $request->validated();

        $role = $this->roles->create($attributes);
        $this->activityLog->recordForUser($request->user(), 'create', 'roles', 'Role created.', $role, null, $role->only(['name', 'slug', 'description', 'is_active']));

        return response()->json([
            'data' => $role,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update an existing role.
     */
    public function update(
        UpdateRoleRequest $request,
        Role $role,
    ): JsonResponse {
        /** @var array{
         *     name: string,
         *     slug: string,
         *     description?: string|null,
         *     is_active?: bool
         * } $attributes
         */
        $attributes = $request->validated();

        $before = $role->only(['name', 'slug', 'description', 'is_active']);
        $role = $this->roles->update(
            $role,
            $attributes,
        );
        $this->activityLog->recordForUser($request->user(), 'update', 'roles', 'Role updated.', $role, $before, $role->only(['name', 'slug', 'description', 'is_active']));

        return response()->json([
            'data' => $role,
        ]);
    }

    /**
     * Synchronize permissions assigned to a non-system role.
     */
    public function syncPermissions(
        SyncRolePermissionsRequest $request,
        Role $role,
    ): JsonResponse {
        $before = $role->permissions()->pluck('permissions.id')->all();
        $permissions = $this->roles->syncPermissions(
            $role,
            $request->validated('permission_ids'),
        );
        $this->activityLog->recordForUser($request->user(), 'permission_change', 'roles', 'Role permissions changed.', $role, ['permission_ids' => $before], ['permission_ids' => $permissions->pluck('id')->all()]);

        return response()->json([
            'data' => $permissions,
        ]);
    }
}
