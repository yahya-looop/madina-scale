<?php

use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogService;
use Database\Seeders\SystemAuthorizationSeeder;

test('activity snapshots redact credential key variants recursively', function () {
    $log = app(ActivityLogService::class)->recordSystem(
        action: 'security.test',
        module: 'security',
        description: 'Sensitive snapshot redaction test',
        oldValues: [
            'api_key' => 'api-secret-value',
            'apiSecret' => 'api-secret-value-2',
            'webhook-secret' => 'webhook-secret-value',
            'Authorization' => 'Bearer private-token',
            'Cookie' => 'session=private',
            'nested' => [
                'otp_code' => '123456',
                'verification_code' => '654321',
                'refreshToken' => 'refresh-private',
                'safe_label' => 'visible',
                'secretary_name' => 'visible',
            ],
        ],
        metadata: [
            'headers' => [
                'X-API-Key' => 'header-private',
                'private_key' => 'private-key-value',
            ],
        ],
    );

    expect($log->fresh()->old_values)->toMatchArray([
        'api_key' => '[REDACTED]',
        'apiSecret' => '[REDACTED]',
        'webhook-secret' => '[REDACTED]',
        'Authorization' => '[REDACTED]',
        'Cookie' => '[REDACTED]',
        'nested' => [
            'otp_code' => '[REDACTED]',
            'verification_code' => '[REDACTED]',
            'refreshToken' => '[REDACTED]',
            'safe_label' => 'visible',
            'secretary_name' => 'visible',
        ],
    ])->and($log->fresh()->metadata)->toMatchArray([
        'headers' => [
            'X-API-Key' => '[REDACTED]',
            'private_key' => '[REDACTED]',
        ],
    ]);
});

test('activity log API does not serialize polymorphic subject models', function () {
    app(SystemAuthorizationSeeder::class)->run();

    $developer = User::factory()->create();
    $developerRole = Role::query()
        ->where('slug', 'developer')
        ->where('is_system', true)
        ->where('is_active', true)
        ->firstOrFail();
    $developer->roles()->syncWithoutDetaching([$developerRole->id]);

    $subject = User::factory()->create([
        'name' => 'Private Subject Account',
        'email' => 'private-subject@example.test',
    ]);

    $log = ActivityLog::query()->create([
        'actor_user_id' => $developer->id,
        'actor_type' => 'system',
        'action' => 'security.subject_test',
        'module' => 'security',
        'description' => 'Subject serialization regression test',
        'subject_type' => User::class,
        'subject_id' => $subject->id,
        'created_at' => now(),
    ]);

    $this->actingAs($developer)
        ->getJson('/api/activity-logs')
        ->assertOk()
        ->assertJsonMissingPath('data.0.subject')
        ->assertJsonMissing(['email' => 'private-subject@example.test']);

    $this->actingAs($developer)
        ->getJson("/api/activity-logs/{$log->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.subject')
        ->assertJsonMissing(['email' => 'private-subject@example.test'])
        ->assertJsonPath('data.actor_type', 'system')
        ->assertJsonPath('data.actor_user_id', null)
        ->assertJsonPath('data.actor', null);

    expect($log->fresh()->actor_user_id)->toBe($developer->id);
});

test('activity log API denies users without the activity log permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/activity-logs')
        ->assertForbidden();
});
