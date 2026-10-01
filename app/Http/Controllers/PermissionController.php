<?php

namespace App\Http\Controllers;

use App\Http\Requests\Permission\StorePermissionRequest;
use App\Http\Requests\Permission\UpdatePermissionRequest;
use App\Models\Permission;
use App\Services\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PermissionController extends Controller
{
    public function __construct(
        protected PermissionService $permissions,
    ) {}

    /**
     * Display all permissions available for management.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->permissions->list(),
        ]);
    }

    /**
     * Display a single permission.
     */
    public function show(Permission $permission): JsonResponse
    {
        $permission->load('module');

        return response()->json([
            'data' => $permission,
        ]);
    }

    /**
     * Store a newly created permission.
     */
    public function store(StorePermissionRequest $request): JsonResponse
    {
        /** @var array{
         *     module_id: int,
         *     name: string,
         *     slug: string,
         *     description?: string|null
         * } $attributes
         */
        $attributes = $request->validated();

        $permission = $this->permissions->create($attributes);

        $permission->load('module');

        return response()->json([
            'data' => $permission,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update an existing permission.
     */
    public function update(
        UpdatePermissionRequest $request,
        Permission $permission,
    ): JsonResponse {
        /** @var array{
         *     module_id: int,
         *     name: string,
         *     slug: string,
         *     description?: string|null
         * } $attributes
         */
        $attributes = $request->validated();

        $permission = $this->permissions->update(
            $permission,
            $attributes,
        );

        $permission->load('module');

        return response()->json([
            'data' => $permission,
        ]);
    }
}
