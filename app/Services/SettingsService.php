<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Collection;

class SettingsService
{
    /**
     * Get a setting value by key.
     */
    public function get(
        string $key,
        mixed $default = null,
    ): mixed {
        $setting = $this->find($key);

        if ($setting === null || ! $setting->is_active) {
            return $default;
        }

        return $this->castValue(
            $setting->value,
            $setting->type,
            $default,
        );
    }

    /**
     * Find a setting by key.
     */
    public function find(string $key): ?Setting
    {
        return Setting::query()
            ->where('key', $key)
            ->first();
    }

    /**
     * Get the typed value of a setting model.
     */
    public function valueOf(
        Setting $setting,
        mixed $default = null,
    ): mixed {
        return $this->castValue(
            $setting->value,
            $setting->type,
            $default,
        );
    }

    /**
     * Create or update a setting.
     *
     * Existing setting metadata is preserved during value updates.
     */
    public function set(
        string $key,
        mixed $value,
        string $group = 'general',
        string $type = 'string',
        ?string $description = null,
        bool $isActive = true,
        int $sortOrder = 0,
    ): Setting {
        $setting = $this->find($key);

        if ($setting === null) {
            $setting = new Setting([
                'key' => $key,
                'group' => $group,
                'value' => $this->serializeValue($value, $type),
                'type' => $type,
                'description' => $description,
                'is_active' => $isActive,
                'sort_order' => $sortOrder,
            ]);

            $setting->save();

            return $setting;
        }

        $setting->value = $this->serializeValue($value, $type);
        $setting->type = $type;
        $setting->save();

        return $setting->refresh();
    }

    /**
     * Get all active settings for a group.
     *
     * @return Collection<string, mixed>
     */
    public function getGroup(string $group): Collection
    {
        return Setting::query()
            ->active()
            ->group($group)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function (Setting $setting): array {
                return [
                    $setting->key => $this->castValue(
                        $setting->value,
                        $setting->type,
                    ),
                ];
            });
    }

    /**
     * Get all active settings.
     *
     * @return Collection<string, mixed>
     */
    public function all(): Collection
    {
        return Setting::query()
            ->active()
            ->orderBy('group')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->mapWithKeys(function (Setting $setting): array {
                return [
                    $setting->key => $this->castValue(
                        $setting->value,
                        $setting->type,
                    ),
                ];
            });
    }

    /**
     * Determine whether an active setting exists.
     */
    public function has(string $key): bool
    {
        return Setting::query()
            ->active()
            ->where('key', $key)
            ->exists();
    }

    /**
     * Deactivate a setting.
     */
    public function deactivate(string $key): bool
    {
        return Setting::query()
            ->where('key', $key)
            ->update(['is_active' => false]) > 0;
    }

    /**
     * Activate a setting.
     */
    public function activate(string $key): bool
    {
        return Setting::query()
            ->where('key', $key)
            ->update(['is_active' => true]) > 0;
    }

    /**
     * Cast a stored setting value to its configured type.
     */
    private function castValue(
        ?string $value,
        string $type,
        mixed $default = null,
    ): mixed {
        if ($value === null) {
            return $default;
        }

        return match ($type) {
            'boolean', 'bool' => filter_var(
                $value,
                FILTER_VALIDATE_BOOLEAN,
                FILTER_NULL_ON_FAILURE,
            ) ?? $default,

            'integer', 'int' => is_numeric($value)
                ? (int) $value
                : $default,

            'float', 'double', 'decimal' => is_numeric($value)
                ? (float) $value
                : $default,

            'json', 'array', 'object' => $this->decodeJson(
                $value,
                $default,
            ),

            default => $value,
        };
    }

    /**
     * Serialize a value before storing it.
     */
    private function serializeValue(
        mixed $value,
        string $type,
    ): ?string {
        if ($value === null) {
            return null;
        }

        if (in_array($type, ['json', 'array', 'object'], true)) {
            return json_encode(
                $value,
                JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
            );
        }

        if (in_array($type, ['boolean', 'bool'], true)) {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }

    /**
     * Decode a JSON setting value safely.
     */
    private function decodeJson(
        string $value,
        mixed $default = null,
    ): mixed {
        try {
            return json_decode(
                $value,
                true,
                512,
                JSON_THROW_ON_ERROR,
            );
        } catch (\JsonException) {
            return $default;
        }
    }
}
