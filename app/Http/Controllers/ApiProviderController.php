<?php

namespace App\Http\Controllers;

use App\Models\ApiProvider;
use App\Services\ActivityLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ApiProviderController extends Controller
{
    public function __construct(private readonly ActivityLogService $activityLogs) {}

    public function index(): JsonResponse
    {
        $providers = ApiProvider::query()->orderBy('category')->orderBy('name')->get()
            ->map(fn (ApiProvider $provider) => $this->safePayload($provider));

        return response()->json(['data' => $providers]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?: $this->uniqueSlug(Str::slug($data['name']));
        $provider = ApiProvider::create($data);

        $this->activityLogs->recordForUser(
            user: $request->user(), action: 'api_provider.created', module: 'developer.api',
            description: 'API provider configuration created.', newValues: $this->auditPayload($provider),
        );

        return response()->json(['data' => $this->safePayload($provider)], 201);
    }

    public function update(Request $request, ApiProvider $apiProvider): JsonResponse
    {
        $data = $this->validated($request, $apiProvider);
        if (empty($data['credentials'] ?? [])) {
            unset($data['credentials']);
        } else {
            $existingCredentialsValue = $apiProvider->getAttribute('credentials');
            $existingCredentials = is_array($existingCredentialsValue) ? $existingCredentialsValue : [];
            $submittedCredentials = $data['credentials'];
            $data['credentials'] = array_merge(
                $existingCredentials,
                array_filter($submittedCredentials, fn ($value) => $value !== null && $value !== ''),
            );
        }
        $old = $this->auditPayload($apiProvider);
        $apiProvider->update($data);

        $this->activityLogs->recordForUser(
            user: $request->user(), action: 'api_provider.updated', module: 'developer.api',
            description: 'API provider configuration updated.', subject: $apiProvider,
            oldValues: $old, newValues: $this->auditPayload($apiProvider),
        );

        return response()->json(['data' => $this->safePayload($apiProvider->fresh())]);
    }

    public function destroy(Request $request, ApiProvider $apiProvider): JsonResponse
    {
        $snapshot = $this->auditPayload($apiProvider);
        $apiProvider->delete();
        $this->activityLogs->recordForUser(
            user: $request->user(), action: 'api_provider.deleted', module: 'developer.api',
            description: 'API provider configuration deleted.', oldValues: $snapshot,
        );

        return response()->json(['message' => 'API provider deleted.']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?ApiProvider $provider = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:140', 'alpha_dash', Rule::unique('api_providers', 'slug')->ignore($provider?->id)],
            'category' => ['required', Rule::in(['payment', 'courier', 'sms', 'email', 'head_office', 'analytics', 'other'])],
            'base_url' => ['required', 'url', 'max:2048'],
            'auth_type' => ['required', Rule::in(['none', 'api_key', 'bearer', 'basic', 'oauth2', 'custom'])],
            'credentials' => ['sometimes', 'array'],
            'credentials.*' => ['nullable', 'string', 'max:4000'],
            'config' => ['sometimes', 'array'],
            'config.*' => ['nullable', 'string', 'max:4000'],
            'environment' => ['required', Rule::in(['sandbox', 'production'])],
            'is_active' => ['required', 'boolean'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }

    private function uniqueSlug(string $base): string
    {
        $slug = $base !== '' ? $base : 'api-provider';
        $candidate = $slug;
        $suffix = 2;
        while (ApiProvider::query()->where('slug', $candidate)->exists()) {
            $candidate = $slug.'-'.$suffix++;
        }

        return $candidate;
    }

    /**
     * @return array<string, mixed>
     */
    private function safePayload(ApiProvider $provider): array
    {
        $credentials = $provider->getAttribute('credentials');
        $credentialKeys = is_array($credentials) ? array_keys($credentials) : [];

        return [
            'id' => $provider->id, 'name' => $provider->name, 'slug' => $provider->slug,
            'category' => $provider->category, 'base_url' => $provider->base_url,
            'auth_type' => $provider->auth_type, 'environment' => $provider->environment,
            'is_active' => $provider->is_active, 'notes' => $provider->notes,
            'has_credentials' => ! empty($provider->credentials),
            'credential_keys' => $credentialKeys,
            'updated_at' => $provider->updated_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function auditPayload(ApiProvider $provider): array
    {
        return [
            'name' => $provider->name, 'slug' => $provider->slug,
            'category' => $provider->category, 'base_url' => $provider->base_url,
            'auth_type' => $provider->auth_type, 'environment' => $provider->environment,
            'is_active' => $provider->is_active, 'has_credentials' => ! empty($provider->credentials),
        ];
    }
}
