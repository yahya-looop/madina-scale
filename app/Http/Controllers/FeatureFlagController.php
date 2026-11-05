<?php

namespace App\Http\Controllers;

use App\Models\FeatureFlag;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\AuthorizationService;
use App\Services\FeatureFlagService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FeatureFlagController extends Controller
{
    public function __construct(
        private readonly FeatureFlagService $flags,
        private readonly AuthorizationService $authorization,
        private readonly ActivityLogService $activityLog,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->developer($request);

        return response()->json(['data' => $this->flags->list()]);
    }

    public function store(Request $request): JsonResponse
    {
        $user = $this->developer($request);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'key' => ['required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', 'unique:feature_flags,key'],
            'description' => ['nullable', 'string', 'max:2000'],
            'module_id' => ['nullable', 'integer', 'exists:modules,id'],
            'is_enabled' => ['sometimes', 'boolean'],
            'rollout_percentage' => ['sometimes', 'integer', 'between:0,100'],
            'environment' => ['sometimes', Rule::in(['all', 'local', 'testing', 'staging', 'production'])],
        ]);

        $flag = DB::transaction(function () use ($data, $user): FeatureFlag {
            $created = $this->flags->create($data);
            $this->activityLog->recordForUser($user, 'feature_flag.created', 'system', "Feature flag {$created->key} created.", $created, null, $created->only(['key', 'name', 'description', 'module_id', 'is_enabled', 'rollout_percentage', 'environment']));

            return $created;
        });

        return response()->json(['data' => $flag], 201);
    }

    public function update(Request $request, FeatureFlag $featureFlag): JsonResponse
    {
        $user = $this->developer($request);
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:150'],
            'key' => ['sometimes', 'required', 'string', 'max:150', 'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/', Rule::unique('feature_flags', 'key')->ignore($featureFlag->id)],
            'description' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'module_id' => ['sometimes', 'nullable', 'integer', 'exists:modules,id'],
            'is_enabled' => ['sometimes', 'boolean'],
            'rollout_percentage' => ['sometimes', 'integer', 'between:0,100'],
            'environment' => ['sometimes', Rule::in(['all', 'local', 'testing', 'staging', 'production'])],
        ]);

        $flag = DB::transaction(function () use ($featureFlag, $data, $user): FeatureFlag {
            $old = $featureFlag->only(['key', 'name', 'description', 'module_id', 'is_enabled', 'rollout_percentage', 'environment']);
            $updated = $this->flags->update($featureFlag, $data);
            $this->activityLog->recordForUser($user, 'feature_flag.updated', 'system', "Feature flag {$updated->key} updated.", $updated, $old, $updated->only(array_keys($old)));

            return $updated;
        });

        return response()->json(['data' => $flag]);
    }

    public function destroy(Request $request, FeatureFlag $featureFlag): JsonResponse
    {
        $user = $this->developer($request);
        abort_if($featureFlag->is_system, 403, 'System feature flags cannot be deleted.');
        DB::transaction(function () use ($featureFlag, $user): void {
            $old = $featureFlag->only(['key', 'name', 'description', 'module_id', 'is_enabled', 'rollout_percentage', 'environment']);
            $this->flags->delete($featureFlag);
            $this->activityLog->recordForUser($user, 'feature_flag.deleted', 'system', "Feature flag {$featureFlag->key} deleted.", null, $old, null);
        });

        return response()->json(['message' => 'Feature flag deleted.']);
    }

    private function developer(Request $request): User
    {
        $user = $request->user();
        abort_unless($user !== null, 401);
        abort_unless($this->authorization->isDeveloper($user), 403);

        return $user;
    }
}
