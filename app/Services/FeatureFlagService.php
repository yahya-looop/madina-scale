<?php

namespace App\Services;

use App\Models\FeatureFlag;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class FeatureFlagService
{
    /** @return Collection<int, FeatureFlag> */
    public function list(): Collection
    {
        return FeatureFlag::query()->with('module:id,name,slug,state')->orderBy('key')->get();
    }

    public function enabled(string $key, ?User $user = null, ?string $context = null): bool
    {
        $flag = FeatureFlag::query()->with('module')->where('key', $key)->first();

        if ($flag === null || ! $flag->is_enabled) {
            return false;
        }

        if (! in_array($flag->environment, ['all', app()->environment()], true)) {
            return false;
        }

        if ($flag->module !== null && (! $flag->module->is_active || in_array($flag->module->state, ['disabled', 'locked'], true))) {
            return false;
        }

        if ($flag->rollout_percentage >= 100) {
            return true;
        }

        if ($flag->rollout_percentage <= 0) {
            return false;
        }

        $identity = $user?->getAuthIdentifier() ?? $context;
        if ($identity === null || $identity === '') {
            return false;
        }

        $bucket = (int) (hexdec(substr(hash('sha256', $flag->key.'|'.$identity), 0, 7)) % 100);

        return $bucket < $flag->rollout_percentage;
    }

    /** @param array{name:string,key:string,description?:string|null,module_id?:int|null,is_enabled?:bool,rollout_percentage?:int,environment?:string} $data */
    public function create(array $data): FeatureFlag
    {
        return DB::transaction(fn (): FeatureFlag => FeatureFlag::query()->create([
            ...$data,
            'is_system' => false,
        ]));
    }

    /** @param array{name?:string,key?:string,description?:string|null,module_id?:int|null,is_enabled?:bool,rollout_percentage?:int,environment?:string} $data */
    public function update(FeatureFlag $flag, array $data): FeatureFlag
    {
        if ($flag->is_system) {
            foreach (['key', 'module_id'] as $protectedField) {
                if (array_key_exists($protectedField, $data) && $data[$protectedField] !== $flag->{$protectedField}) {
                    abort(403, 'System feature flag keys and module bindings are protected.');
                }
            }
        }

        return DB::transaction(function () use ($flag, $data): FeatureFlag {
            $flag->update($data);

            return $flag->refresh();
        });
    }

    public function delete(FeatureFlag $flag): void
    {
        abort_if($flag->is_system, 403, 'System feature flags cannot be deleted.');
        $flag->delete();
    }
}
