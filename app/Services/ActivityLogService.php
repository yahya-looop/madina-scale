<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    /**
     * Fields that must never be persisted into audit snapshots.
     *
     * Activity logs are intended for traceability, not for storing
     * authentication secrets.
     */
    private const SENSITIVE_KEY_SEGMENTS = [
        'password',
        'passwd',
        'secret',
        'token',
        'authorization',
        'cookie',
        'otp',
        'totp',
        'mfa',
        'pin',
        'private_key',
        'signing_key',
        'api_key',
        'client_key',
        'webhook_key',
        'verification_code',
        'verification_token',
        'recovery_code',
        'backup_code',
        'card_number',
        'cvv',
        'cvc',
    ];

    /**
     * Record an application activity.
     *
     * Developer actions are stored with:
     * - the actual developer user ID for internal traceability
     * - actor_type = system for presentation/privacy rules
     *
     * Non-developer authenticated users are stored as actor_type = user.
     *
     * System/background activities can be recorded with no actor.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        string $action,
        string $module,
        string $description,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
        ?User $actor = null,
    ): ActivityLog {
        $action = trim($action);
        $module = trim($module);
        $description = trim($description);

        if ($action === '') {
            throw new \InvalidArgumentException(
                'Activity log action cannot be empty.',
            );
        }

        if ($module === '') {
            throw new \InvalidArgumentException(
                'Activity log module cannot be empty.',
            );
        }

        if ($description === '') {
            throw new \InvalidArgumentException(
                'Activity log description cannot be empty.',
            );
        }

        /*
         * Resolve the authenticated actor when no explicit actor
         * has been supplied.
         */
        $actor ??= Auth::user();

        /*
         * Developer actions are presented as System.
         *
         * We deliberately keep actor_user_id populated so the Developer
         * can later perform internal forensic tracing without exposing
         * that identity to normal activity-log viewers.
         */
        $actorType = $this->resolveActorType($actor);

        /*
         * request() may not be available during CLI/queue execution.
         * Laravel's request helper is safe here, but we still isolate
         * request metadata behind dedicated methods.
         */
        $request = Request::instance();

        $requestId = $request->attributes->get('request_id');

        if (
            ! is_string($requestId)
            || trim($requestId) === ''
        ) {
            $requestId = $request->header('X-Request-ID');
        }

        if (
            ! is_string($requestId)
            || trim($requestId) === ''
        ) {
            $requestId = null;
        }

        $payload = [
            'actor_user_id' => $actor?->id,
            'actor_type' => $actorType,
            'action' => $action,
            'module' => $module,
            'description' => $description,

            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),

            'old_values' => $this->sanitizeSnapshot(
                $oldValues,
            ),

            'new_values' => $this->sanitizeSnapshot(
                $newValues,
            ),

            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'request_id' => $requestId,

            'metadata' => $this->sanitizeSnapshot(
                $metadata,
            ),

            'created_at' => now(),
        ];

        $payload['integrity_hash'] = $this->makeIntegrityHash($payload);

        return ActivityLog::query()->create($payload);
    }

    /**
     * Record a user-generated activity explicitly.
     *
     * This is useful when a service already knows which user performed
     * the operation and should not rely on the current Auth context.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordForUser(
        User $user,
        string $action,
        string $module,
        string $description,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
    ): ActivityLog {
        return $this->record(
            action: $action,
            module: $module,
            description: $description,
            subject: $subject,
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: $metadata,
            actor: $user,
        );
    }

    /**
     * Record a genuine system/background activity.
     *
     * This is different from a Developer action:
     * no user actor is attached.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     * @param  array<string, mixed>|null  $metadata
     */
    public function recordSystem(
        string $action,
        string $module,
        string $description,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null,
    ): ActivityLog {
        return $this->record(
            action: $action,
            module: $module,
            description: $description,
            subject: $subject,
            oldValues: $oldValues,
            newValues: $newValues,
            metadata: $metadata,
            actor: null,
        );
    }

    /**
     * Create a keyed integrity digest so unauthorized database edits can be detected.
     *
     * @param  array<string, mixed>  $payload
     */
    private function makeIntegrityHash(array $payload): string
    {
        unset($payload['integrity_hash']);
        foreach (['old_values', 'new_values', 'metadata'] as $jsonField) {
            $payload[$jsonField] = $this->sortRecursively($payload[$jsonField] ?? null);
        }
        unset($payload['created_at']);
        $canonical = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);

        return hash_hmac('sha256', $canonical === false ? '' : $canonical, (string) config('app.key'));
    }

    private function sortRecursively(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->sortRecursively($item);
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }

    /**
     * Determine how the actor should be presented.
     *
     * Developer is deliberately represented as System.
     */
    protected function resolveActorType(?User $actor): string
    {
        if ($actor === null) {
            return 'system';
        }

        if ($this->isDeveloper($actor)) {
            return 'system';
        }

        return 'user';
    }

    /**
     * Determine whether the supplied user is the active Developer.
     *
     * This mirrors the existing AuthorizationService rule:
     * immutable "developer" slug + system role + active role.
     */
    protected function isDeveloper(User $user): bool
    {
        return $user->roles()
            ->where('roles.slug', 'developer')
            ->where('roles.is_system', true)
            ->where('roles.is_active', true)
            ->exists();
    }

    /**
     * Remove sensitive fields from an audit snapshot.
     *
     * Nested arrays are recursively sanitized so credentials cannot
     * accidentally enter old_values, new_values, or metadata.
     *
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    protected function sanitizeSnapshot(
        ?array $values,
    ): ?array {
        if ($values === null) {
            return null;
        }

        $sanitized = [];

        foreach ($values as $key => $value) {
            if ($this->isSensitiveKey($key)) {
                $sanitized[$key] = '[REDACTED]';

                continue;
            }

            if (is_array($value)) {
                /** @var array<string, mixed> $nested */
                $nested = $value;

                $sanitized[$key] = $this->sanitizeSnapshot(
                    $nested,
                );

                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }

    /**
     * Determine whether an input key contains sensitive data.
     */
    protected function isSensitiveKey(string $key): bool
    {
        // Normalize camelCase, kebab-case, dotted and header-style keys.
        // Split acronym-to-word and camelCase boundaries before replacing
        // punctuation. Laravel's generic snake() normalization does not
        // consistently split all-uppercase HTTP header segments.
        $key = preg_replace('/([A-Z]+)([A-Z][a-z])/', '$1_$2', $key) ?? $key;
        $key = preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $key) ?? $key;

        $normalizedKey = strtolower($key);
        $normalizedKey = preg_replace('/[^a-z0-9]+/', '_', $normalizedKey) ?? $normalizedKey;
        $normalizedKey = trim($normalizedKey, '_');

        if ($normalizedKey === '') {
            return false;
        }

        $segments = explode('_', $normalizedKey);

        foreach (self::SENSITIVE_KEY_SEGMENTS as $sensitiveSegment) {
            $normalizedSegment = strtolower($sensitiveSegment);
            $normalizedSegment = preg_replace('/[^a-z0-9]+/', '_', $normalizedSegment) ?? $normalizedSegment;
            $normalizedSegment = trim($normalizedSegment, '_');

            // Match explicit sensitive phrases or standalone credential terms.
            $sensitiveParts = explode('_', $normalizedSegment);
            $partCount = count($sensitiveParts);

            for ($offset = 0; $offset <= count($segments) - $partCount; $offset++) {
                if (array_slice($segments, $offset, $partCount) === $sensitiveParts) {
                    return true;
                }
            }
        }

        return false;
    }
}
