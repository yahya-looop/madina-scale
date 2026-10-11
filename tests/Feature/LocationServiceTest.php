
<?php

use App\Models\Location;
use App\Services\LocationService;
use Illuminate\Validation\ValidationException;

function makeLocation(array $overrides = []): Location
{
    return Location::query()->create(array_merge([
        'name' => 'Main Branch',
        'code' => 'MAIN',
        'type' => 'head_office',
        'is_active' => true,
        'is_primary' => true,
        'country_code' => 'BD',
    ], $overrides));
}

test('creating an inactive primary location is rejected without clearing the existing primary', function () {
    $existingPrimary = makeLocation();

    try {
        app(LocationService::class)->create([
            'name' => 'Inactive Branch',
            'code' => 'INACTIVE',
            'type' => 'branch',
            'is_active' => false,
            'is_primary' => true,
            'country_code' => 'BD',
        ]);

        test()->fail('Expected a validation exception.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('is_active');
    }

    expect($existingPrimary->fresh()->is_primary)->toBeTrue()
        ->and(Location::query()->count())->toBe(1);
});

test('creating an active primary location clears the previous primary', function () {
    $existingPrimary = makeLocation();

    $newPrimary = app(LocationService::class)->create([
        'name' => 'New Branch',
        'code' => 'NEW',
        'type' => 'branch',
        'is_active' => true,
        'is_primary' => true,
        'country_code' => 'BD',
    ]);

    expect($existingPrimary->fresh()->is_primary)->toBeFalse()
        ->and($newPrimary->fresh()->is_primary)->toBeTrue()
        ->and(Location::query()->where('is_primary', true)->count())->toBe(1);
});
