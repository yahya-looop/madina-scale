<?php

namespace App\Http\Requests\Settings;

use App\Services\AuthorizationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SettingsUpdateRequest extends FormRequest
{
    /**
     * Registered settings that may be managed through the API.
     *
     * The frontend must never be able to create arbitrary setting keys.
     *
     * @return array<string, array{
     *     group: string,
     *     type: string
     * }>
     */
    public static function definitions(): array
    {
        return [
            /*
            |--------------------------------------------------------------------------
            | General
            |--------------------------------------------------------------------------
            */

            'general.site_name' => [
                'group' => 'general',
                'type' => 'string',
            ],

            'general.site_description' => [
                'group' => 'general',
                'type' => 'string',
            ],

            'general.contact_email' => [
                'group' => 'general',
                'type' => 'string',
            ],

            'general.contact_phone' => [
                'group' => 'general',
                'type' => 'string',
            ],

            'general.support_channels' => [
                'group' => 'general',
                'type' => 'json',
            ],

            /*
            |--------------------------------------------------------------------------
            | Localization
            |--------------------------------------------------------------------------
            */

            'localization.timezone' => [
                'group' => 'localization',
                'type' => 'string',
            ],

            'localization.locale' => [
                'group' => 'localization',
                'type' => 'string',
            ],

            'localization.date_format' => [
                'group' => 'localization',
                'type' => 'string',
            ],

            'localization.time_format' => [
                'group' => 'localization',
                'type' => 'string',
            ],

            'localization.week_starts_on' => [
                'group' => 'localization',
                'type' => 'integer',
            ],

            /*
            |--------------------------------------------------------------------------
            | Currency
            |--------------------------------------------------------------------------
            */

            'currency.default' => [
                'group' => 'currency',
                'type' => 'string',
            ],

            'currency.symbol' => [
                'group' => 'currency',
                'type' => 'string',
            ],

            'currency.decimal_places' => [
                'group' => 'currency',
                'type' => 'integer',
            ],

            'currency.thousand_separator' => [
                'group' => 'currency',
                'type' => 'string',
            ],

            'currency.decimal_separator' => [
                'group' => 'currency',
                'type' => 'string',
            ],

            /*
            |--------------------------------------------------------------------------
            | Business
            |--------------------------------------------------------------------------
            */

            'business.name' => [
                'group' => 'business',
                'type' => 'string',
            ],

            'business.email' => [
                'group' => 'business',
                'type' => 'string',
            ],

            'business.phone' => [
                'group' => 'business',
                'type' => 'string',
            ],

            'business.address' => [
                'group' => 'business',
                'type' => 'string',
            ],

            'business.business_hours' => [
                'group' => 'business',
                'type' => 'json',
            ],

            /*
            |--------------------------------------------------------------------------
            | System
            |--------------------------------------------------------------------------
            */

            'system.maintenance_mode' => [
                'group' => 'system',
                'type' => 'boolean',
            ],

            'system.items_per_page' => [
                'group' => 'system',
                'type' => 'integer',
            ],

            'system.support_channels' => [
                'group' => 'system',
                'type' => 'json',
            ],

            /*
            |--------------------------------------------------------------------------
            | Security
            |--------------------------------------------------------------------------
            |
            | These settings define the configurable security policy.
            | Runtime security services will consume these values in the
            | following Security implementation phase.
            |
            */

            'security.login_max_attempts' => [
                'group' => 'security',
                'type' => 'integer',
            ],

            'security.lockout_duration_minutes' => [
                'group' => 'security',
                'type' => 'integer',
            ],

            'security.session_timeout_minutes' => [
                'group' => 'security',
                'type' => 'integer',
            ],

            'security.password_min_length' => [
                'group' => 'security',
                'type' => 'integer',
            ],

            'security.password_require_mixed_case' => [
                'group' => 'security',
                'type' => 'boolean',
            ],

            'security.password_require_numbers' => [
                'group' => 'security',
                'type' => 'boolean',
            ],

            'security.password_require_symbols' => [
                'group' => 'security',
                'type' => 'boolean',
            ],

            'security.password_expiry_days' => [
                'group' => 'security',
                'type' => 'integer',
            ],

            'security.force_password_change' => [
                'group' => 'security',
                'type' => 'boolean',
            ],

            'security.require_email_verification' => [
                'group' => 'security',
                'type' => 'boolean',
            ],
        ];
    }

    /**
     * Determine whether the authenticated user may update settings.
     */
    public function authorize(
        AuthorizationService $authorizationService,
    ): bool {
        $user = $this->user();

        return $user !== null
            && $authorizationService->can(
                $user,
                'system.settings.update',
            );
    }

    /**
     * Validate the settings payload.
     */
    public function rules(): array
    {
        return [
            'settings' => [
                'required',
                'array',
                'min:1',
            ],

            'settings.*.key' => [
                'required',
                'string',
                'max:150',
                Rule::in(array_keys(self::definitions())),
            ],

            'settings.*.value' => [
                'present',
                'nullable',
            ],

            'settings.*.type' => [
                'required',
                'string',
                Rule::in([
                    'string',
                    'boolean',
                    'bool',
                    'integer',
                    'int',
                    'float',
                    'double',
                    'decimal',
                    'json',
                    'array',
                    'object',
                ]),
            ],
        ];
    }

    /**
     * Normalize scalar values before validation.
     */
    protected function prepareForValidation(): void
    {
        $settings = $this->input('settings');

        if (! is_array($settings)) {
            return;
        }

        $this->merge([
            'settings' => array_map(
                static function (mixed $setting): mixed {
                    if (! is_array($setting)) {
                        return $setting;
                    }

                    if (
                        ! array_key_exists('value', $setting)
                        || ! is_string($setting['value'])
                    ) {
                        return $setting;
                    }

                    $type = $setting['type'] ?? 'string';
                    $value = $setting['value'];

                    if (in_array($type, ['boolean', 'bool'], true)) {
                        $normalized = filter_var(
                            $value,
                            FILTER_VALIDATE_BOOLEAN,
                            FILTER_NULL_ON_FAILURE,
                        );

                        /*
                         * Only normalize values that are actually valid
                         * boolean representations.
                         *
                         * Invalid values are intentionally preserved so
                         * validation can reject them instead of silently
                         * converting them to null.
                         */
                        if ($normalized !== null) {
                            $setting['value'] = $normalized;
                        }

                        return $setting;
                    }

                    if (
                        in_array($type, ['integer', 'int'], true)
                        && is_numeric($value)
                    ) {
                        $setting['value'] = (int) $value;

                        return $setting;
                    }

                    if (
                        in_array(
                            $type,
                            ['float', 'double', 'decimal'],
                            true,
                        )
                        && is_numeric($value)
                    ) {
                        $setting['value'] = (float) $value;
                    }

                    return $setting;
                },
                $settings,
            ),
        ]);
    }

    /**
     * Apply definition-aware validation after standard rules.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(
            function (Validator $validator): void {
                $settings = $this->input('settings', []);

                if (! is_array($settings)) {
                    return;
                }

                $definitions = self::definitions();

                foreach ($settings as $index => $setting) {
                    if (! is_array($setting)) {
                        continue;
                    }

                    $key = $setting['key'] ?? null;
                    $type = $setting['type'] ?? null;

                    if (
                        ! is_string($key)
                        || ! array_key_exists($key, $definitions)
                    ) {
                        continue;
                    }

                    $definition = $definitions[$key];

                    /*
                     * The submitted type must exactly match the
                     * registered backend definition.
                     */
                    if ($type !== $definition['type']) {
                        $validator->errors()->add(
                            "settings.{$index}.type",
                            "The selected type is invalid for {$key}.",
                        );

                        continue;
                    }

                    $value = $setting['value'] ?? null;

                    /*
                     * Nullable values are intentionally allowed because
                     * SettingsService supports nullable setting values.
                     */
                    if ($value === null) {
                        continue;
                    }

                    $valid = match ($definition['type']) {
                        'string' => is_string($value),

                        'boolean' => is_bool($value),

                        'integer' => is_int($value),

                        'json' => is_array($value) || is_object($value),

                        default => true,
                    };

                    if (! $valid) {
                        $validator->errors()->add(
                            "settings.{$index}.value",
                            "The value type is invalid for {$key}.",
                        );
                    }
                }
            },
        );
    }
}
