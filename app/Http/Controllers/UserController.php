<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private readonly UserService $userService,
    ) {
    }

    /**
     * List users available for management.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => $this->userService->list(),
        ]);
    }

    /**
     * Create a new user.
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $user = $this->userService->create($validated);

        if (array_key_exists('role_ids', $validated)) {
            $user = $this->userService->syncRoles(
                $user,
                $validated['role_ids'] ?? [],
            );
        }

        return response()->json([
            'message' => 'User created successfully.',
            'data' => $user->load([
                'roles:id,name,slug,is_system,is_active',
            ]),
        ], 201);
    }

    /**
     * Show a single user.
     */
    public function show(User $user): JsonResponse
    {
        return response()->json([
            'data' => $this->userService->find($user->id),
        ]);
    }

    /**
     * Update an existing user.
     */
    public function update(
        UpdateUserRequest $request,
        User $user,
    ): JsonResponse {
        $validated = $request->validated();

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

        return response()->json([
            'message' => 'User updated successfully.',
            'data' => $user->load([
                'roles:id,name,slug,is_system,is_active',
            ]),
        ]);
    }
}
