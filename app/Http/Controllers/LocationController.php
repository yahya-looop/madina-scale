<?php

namespace App\Http\Controllers;

use App\Http\Requests\Location\StoreLocationRequest;
use App\Http\Requests\Location\UpdateLocationRequest;
use App\Models\Location;
use App\Services\ActivityLogService;
use App\Services\AuthorizationService;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LocationController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly ActivityLogService $activityLogService,
        private readonly LocationService $locationService,
    ) {}

    /**
     * Return all locations.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $this->authorizationService->can(
                $user,
                'system.locations.view',
            ),
            403,
        );

        $activeOnly = $request->boolean('active');

        return response()->json([
            'data' => $this->locationService->all($activeOnly),
        ]);
    }

    /**
     * Return a single location.
     */
    public function show(
        Request $request,
        Location $location,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $this->authorizationService->can(
                $user,
                'system.locations.view',
            ),
            403,
        );

        return response()->json([
            'data' => $location,
        ]);
    }

    /**
     * Create a new location.
     */
    public function store(
        StoreLocationRequest $request,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user !== null, 401);

        $location = $this->locationService->create(
            $request->validated(),
        );

        $this->activityLogService->recordForUser(
            user: $user,
            action: 'locations.created',
            module: 'locations',
            description: "Location '{$location->name}' was created.",
            newValues: $location->toArray(),
        );

        return response()->json([
            'message' => 'Location created successfully.',
            'data' => $location,
        ], 201);
    }

    /**
     * Update an existing location.
     */
    public function update(
        UpdateLocationRequest $request,
        Location $location,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user !== null, 401);

        $oldValues = $location->toArray();

        $location = $this->locationService->update(
            $location,
            $request->validated(),
        );

        $this->activityLogService->recordForUser(
            user: $user,
            action: 'locations.updated',
            module: 'locations',
            description: "Location '{$location->name}' was updated.",
            oldValues: $oldValues,
            newValues: $location->toArray(),
        );

        return response()->json([
            'message' => 'Location updated successfully.',
            'data' => $location,
        ]);
    }

    /**
     * Activate a location.
     */
    public function activate(
        Request $request,
        Location $location,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $this->authorizationService->can(
                $user,
                'system.locations.manage',
            ),
            403,
        );

        $wasActive = $location->is_active;

        $location = $this->locationService->activate($location);

        if (! $wasActive) {
            $this->activityLogService->recordForUser(
                user: $user,
                action: 'locations.activated',
                module: 'locations',
                description: "Location '{$location->name}' was activated.",
                newValues: [
                    'location_id' => $location->id,
                    'is_active' => true,
                ],
            );
        }

        return response()->json([
            'message' => 'Location activated successfully.',
            'data' => $location,
        ]);
    }

    /**
     * Deactivate a location.
     */
    public function deactivate(
        Request $request,
        Location $location,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $this->authorizationService->can(
                $user,
                'system.locations.manage',
            ),
            403,
        );

        if ($location->is_primary) {
            throw ValidationException::withMessages([
                'location' => [
                    'The primary location cannot be deactivated. Assign another primary location first.',
                ],
            ]);
        }

        $location = $this->locationService->deactivate($location);

        $this->activityLogService->recordForUser(
            user: $user,
            action: 'locations.deactivated',
            module: 'locations',
            description: "Location '{$location->name}' was deactivated.",
            newValues: [
                'location_id' => $location->id,
                'is_active' => false,
            ],
        );

        return response()->json([
            'message' => 'Location deactivated successfully.',
            'data' => $location,
        ]);
    }
}
