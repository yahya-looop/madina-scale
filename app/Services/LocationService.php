<?php

namespace App\Services;

use App\Models\Location;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LocationService
{
    /**
     * Get all locations.
     *
     * @return Collection<int, Location>
     */
    public function all(bool $activeOnly = false): Collection
    {
        $query = Location::query()
            ->orderByDesc('is_primary')
            ->orderBy('name');

        if ($activeOnly) {
            $query->active();
        }

        return $query->get();
    }

    /**
     * Find a location by its ID.
     */
    public function find(int $id): Location
    {
        return Location::query()->findOrFail($id);
    }

    /**
     * Create a location.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): Location
    {
        return DB::transaction(function () use ($data): Location {
            $data = $this->normalizeData($data);

            if (($data['is_primary'] ?? false) === true) {
                $this->clearPrimaryLocation();
            }

            return Location::query()->create($data);
        });
    }

    /**
     * Update a location.
     *
     * @param array<string, mixed> $data
     */
    public function update(Location $location, array $data): Location
    {
        return DB::transaction(function () use ($location, $data): Location {
            $data = $this->normalizeData($data);

            $isPrimary = (bool) ($data['is_primary'] ?? false);
            $isActive = (bool) ($data['is_active'] ?? $location->is_active);

            if ($isPrimary && ! $isActive) {
                throw ValidationException::withMessages([
                    'is_active' => 'A primary location must remain active.',
                ]);
            }

            if ($isPrimary) {
                $this->clearPrimaryLocation($location->getKey());
            }

            $location->fill($data);
            $location->save();

            return $location->refresh();
        });
    }

    /**
     * Deactivate a location safely.
     */
    public function deactivate(Location $location): Location
    {
        if ($location->is_primary) {
            throw ValidationException::withMessages([
                'is_active' => 'The primary location cannot be deactivated. Assign another primary location first.',
            ]);
        }

        $location->forceFill([
            'is_active' => false,
        ])->save();

        return $location->refresh();
    }

    /**
     * Activate a location.
     */
    public function activate(Location $location): Location
    {
        $location->forceFill([
            'is_active' => true,
        ])->save();

        return $location->refresh();
    }

    /**
     * Normalize location input before persistence.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeData(array $data): array
    {
        if (isset($data['name']) && is_string($data['name'])) {
            $data['name'] = trim($data['name']);
        }

        if (isset($data['code']) && is_string($data['code'])) {
            $data['code'] = strtoupper(trim($data['code']));
        }

        if (isset($data['country_code']) && is_string($data['country_code'])) {
            $data['country_code'] = strtoupper(trim($data['country_code']));
        }

        $data['is_active'] = (bool) ($data['is_active'] ?? true);
        $data['is_primary'] = (bool) ($data['is_primary'] ?? false);

        return $data;
    }

    /**
     * Clear the current primary location.
     */
    private function clearPrimaryLocation(?int $exceptId = null): void
    {
        $query = Location::query()
            ->where('is_primary', true);

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        $query->update([
            'is_primary' => false,
        ]);
    }
}