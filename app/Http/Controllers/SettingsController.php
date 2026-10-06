<?php

namespace App\Http\Controllers;

use App\Http\Requests\Settings\SettingsUpdateRequest;
use App\Services\ActivityLogService;
use App\Services\AuthorizationService;
use App\Services\SettingsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(
        private readonly AuthorizationService $authorizationService,
        private readonly ActivityLogService $activityLogService,
        private readonly SettingsService $settingsService,
    ) {
    }

    /**
     * Return all active system settings.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $this->authorizationService->can(
                $user,
                'system.settings.view',
            ),
            403,
        );

        return response()->json([
            'data' => $this->settingsService->all(),
        ]);
    }

    /**
     * Return settings for the system group.
     */
    public function system(Request $request): JsonResponse
    {
        return $this->group($request, 'system');
    }

    /**
     * Return settings for a specific group.
     */
    public function group(
        Request $request,
        string $group,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user !== null, 401);

        abort_unless(
            $this->authorizationService->can(
                $user,
                'system.settings.view',
            ),
            403,
        );

        return response()->json([
            'data' => $this->settingsService->getGroup($group),
        ]);
    }

    /**
     * Update one or more registered system settings.
     */
    public function update(
        SettingsUpdateRequest $request,
    ): JsonResponse {
        $user = $request->user();

        abort_unless($user !== null, 401);

        $validated = $request->validated();
        $definitions = SettingsUpdateRequest::definitions();

        $updatedSettings = [];
        $oldValues = [];
        $newValues = [];

        foreach ($validated['settings'] as $settingData) {
            $key = $settingData['key'];
            $definition = $definitions[$key];

            $existingSetting = $this->settingsService->find($key);

            $oldValues[$key] = $existingSetting === null
                ? null
                : $this->settingsService->valueOf($existingSetting);

            $updatedSetting = $this->settingsService->set(
                key: $key,
                value: $settingData['value'],
                group: $definition['group'],
                type: $definition['type'],
            );

            $newValues[$key] = $this->settingsService->valueOf(
                $updatedSetting,
            );

            $updatedSettings[] = $updatedSetting;
        }

        $this->activityLogService->recordForUser(
            user: $user,
            action: 'settings.updated',
            module: 'settings',
            description: 'System settings were updated.',
            oldValues: $oldValues,
            newValues: $newValues,
        );

        return response()->json([
            'message' => 'Settings updated successfully.',
            'data' => $updatedSettings,
        ]);
    }
}
