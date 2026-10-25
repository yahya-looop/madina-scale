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

            'business.legal_name' => [
                'group' => 'business',
                'type' => 'string',
            ],

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

            'business.website' => [
                'group' => 'business',
                'type' => 'string',
            ],

            'business.address' => [
                'group' => 'business',
                'type' => 'string',
            ],

            'business.country_code' => [
                'group' => 'business',
                'type' => 'string',
            ],

            'business.division' => [
                'group' => 'business',
                'type' => 'string',
            ],

            'business.district' => [
                'group' => 'business',
                'type' => 'string',
            ],

            'business.postal_code' => [
                'group' => 'business',
                'type' => 'string',
            ],

            'business.type' => [
                'group' => 'business',
                'type' => 'string',
            ],

            'business.industry' => [
                'group' => 'business',
                'type' => 'string',
            ],

            'business.registration_number' => [
                'group' => 'business',
                'type' => 'string',
            ],

            'business.bin' => [
                'group' => 'business',
                'type' => 'string',
            ],

            'business.tin' => [
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
    /**
     * Apply definition-aware and domain-aware validation.
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
                $submitted = [];

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

                    $submitted[$key] = $value;

                    /*
                     * Nullable values are intentionally supported by
                     * SettingsService and remain valid.
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

                /*
                |--------------------------------------------------------------------------
                | Business Foundation
                |--------------------------------------------------------------------------
                |
                | Build validation around the complete submitted settings map
                | instead of looking for sibling values inside one setting item.
                |
                */

                $businessStringRules = [
                    'business.legal_name' => 200,
                    'business.name' => 200,
                    'business.phone' => 30,
                    'business.website' => 255,
                    'business.address' => 1000,
                    'business.division' => 120,
                    'business.district' => 120,
                    'business.postal_code' => 20,
                    'business.type' => 50,
                    'business.industry' => 120,
                    'business.registration_number' => 100,
                    'business.bin' => 50,
                    'business.tin' => 50,
                ];

                foreach ($businessStringRules as $key => $maxLength) {
                    if (! array_key_exists($key, $submitted)) {
                        continue;
                    }

                    $value = $submitted[$key];

                    if ($value === null || $value === '') {
                        continue;
                    }

                    if (! is_string($value)) {
                        continue;
                    }

                    if (mb_strlen(trim($value)) > $maxLength) {
                        $validator->errors()->add(
                            'settings.'.$this->settingIndex($settings, $key).'.value',
                            "The {$key} value may not exceed {$maxLength} characters.",
                        );
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Business Email
                |--------------------------------------------------------------------------
                */

                if (array_key_exists('business.email', $submitted)) {
                    $email = trim((string) $submitted['business.email']);

                    if (
                        $email !== ''
                        && filter_var($email, FILTER_VALIDATE_EMAIL) === false
                    ) {
                        $validator->errors()->add(
                            'settings.'.$this->settingIndex($settings, 'business.email').'.value',
                            'The business email address must be valid.',
                        );
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Business Website
                |--------------------------------------------------------------------------
                */

                if (array_key_exists('business.website', $submitted)) {
                    $website = trim((string) $submitted['business.website']);

                    if (
                        $website !== ''
                        && filter_var($website, FILTER_VALIDATE_URL) === false
                    ) {
                        $validator->errors()->add(
                            'settings.'.$this->settingIndex($settings, 'business.website').'.value',
                            'The business website must be a valid URL.',
                        );
                    }
                }

                /*
                |--------------------------------------------------------------------------
                | Country
                |--------------------------------------------------------------------------
                */

                $countryCode = strtoupper(
                    trim((string) ($submitted['business.country_code'] ?? ''))
                );

                if (
                    $countryCode !== ''
                    && ! preg_match('/^[A-Z]{2}$/', $countryCode)
                ) {
                    $validator->errors()->add(
                        'settings.'.$this->settingIndex($settings, 'business.country_code').'.value',
                        'The business country code must use a valid two-letter format.',
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Bangladesh Postal Code
                |--------------------------------------------------------------------------
                |
                | Bangladesh postal codes are four numeric digits.
                |
                */

                $postalCode = trim(
                    (string) ($submitted['business.postal_code'] ?? '')
                );

                if (
                    $postalCode !== ''
                    && $countryCode === 'BD'
                    && ! preg_match('/^\d{4}$/', $postalCode)
                ) {
                    $validator->errors()->add(
                        'settings.'.$this->settingIndex($settings, 'business.postal_code').'.value',
                        'Bangladesh postal codes must contain exactly 4 digits.',
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Business Type
                |--------------------------------------------------------------------------
                */

                $businessType = trim(
                    (string) ($submitted['business.type'] ?? '')
                );

                $allowedBusinessTypes = [
                    'sole_proprietorship',
                    'partnership',
                    'private_limited',
                    'public_limited',
                    'llc',
                    'ngo',
                    'government',
                    'other',
                ];

                if (
                    $businessType !== ''
                    && ! in_array($businessType, $allowedBusinessTypes, true)
                ) {
                    $validator->errors()->add(
                        'settings.'.$this->settingIndex($settings, 'business.type').'.value',
                        'The selected business type is invalid.',
                    );
                }

                /*
                |--------------------------------------------------------------------------
                | Business Hours
                |--------------------------------------------------------------------------
                */

                if (
                    array_key_exists('business.business_hours', $submitted)
                    && $submitted['business.business_hours'] !== null
                ) {
                    $hours = $submitted['business.business_hours'];

                    if (! is_array($hours)) {
                        $validator->errors()->add(
                            'settings.'.$this->settingIndex($settings, 'business.business_hours').'.value',
                            'Business hours must be a valid weekly schedule.',
                        );
                    } else {
                        $validDays = [
                            'saturday',
                            'sunday',
                            'monday',
                            'tuesday',
                            'wednesday',
                            'thursday',
                            'friday',
                        ];

                        foreach ($hours as $day => $schedule) {
                            if (! in_array($day, $validDays, true)) {
                                $validator->errors()->add(
                                    'settings.'.$this->settingIndex($settings, 'business.business_hours').'.value',
                                    "Invalid business hours day: {$day}.",
                                );

                                continue;
                            }

                            if (! is_array($schedule)) {
                                $validator->errors()->add(
                                    'settings.'.$this->settingIndex($settings, 'business.business_hours').'.value',
                                    "The {$day} business hours configuration is invalid.",
                                );

                                continue;
                            }

                            $enabled = $schedule['enabled'] ?? false;
                            $open = $schedule['open'] ?? null;
                            $close = $schedule['close'] ?? null;

                            if (! is_bool($enabled)) {
                                $validator->errors()->add(
                                    'settings.'.$this->settingIndex($settings, 'business.business_hours').'.value',
                                    "The {$day} enabled value must be boolean.",
                                );

                                continue;
                            }

                            if (! $enabled) {
                                continue;
                            }

                            if (
                                ! is_string($open)
                                || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $open)
                            ) {
                                $validator->errors()->add(
                                    'settings.'.$this->settingIndex($settings, 'business.business_hours').'.value',
                                    "The {$day} opening time is invalid.",
                                );
                            }

                            if (
                                ! is_string($close)
                                || ! preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $close)
                            ) {
                                $validator->errors()->add(
                                    'settings.'.$this->settingIndex($settings, 'business.business_hours').'.value',
                                    "The {$day} closing time is invalid.",
                                );
                            }

                            if (
                                is_string($open)
                                && is_string($close)
                                && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $open)
                                && preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $close)
                                && $open >= $close
                            ) {
                                $validator->errors()->add(
                                    'settings.'.$this->settingIndex($settings, 'business.business_hours').'.value',
                                    "The {$day} closing time must be later than the opening time.",
                                );
                            }
                        }
                    }
                }
            },
        );
    }

    /**
     * Find the submitted array index for a setting key.
     */
    private function settingIndex(array $settings, string $key): int
    {
        foreach ($settings as $index => $setting) {
            if (
                is_array($setting)
                && ($setting['key'] ?? null) === $key
            ) {
                return (int) $index;
            }
        }

        return 0;
    }
}
