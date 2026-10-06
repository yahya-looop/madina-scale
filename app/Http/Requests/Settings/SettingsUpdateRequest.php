<?php

namespace App\Http\Requests\Settings;

use App\Services\AuthorizationService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
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

            // Existing system settings.
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
        ];
    }

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
                'in:' . implode(',', array_keys(self::definitions())),
            ],

            'settings.*.value' => [
                'present',
                'nullable',
            ],

            'settings.*.type' => [
                'required',
                'string',
                'in:string,boolean,bool,integer,int,float,double,decimal,json,array,object',
            ],
        ];
    }

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
                        array_key_exists('value', $setting)
                        && is_string($setting['value'])
                    ) {
                        $type = $setting['type'] ?? 'string';

                        if (in_array($type, ['boolean', 'bool'], true)) {
                            $setting['value'] = filter_var(
                                $setting['value'],
                                FILTER_VALIDATE_BOOLEAN,
                                FILTER_NULL_ON_FAILURE,
                            );
                        }

                        if (
                            in_array($type, ['integer', 'int'], true)
                            && is_numeric($setting['value'])
                        ) {
                            $setting['value'] = (int) $setting['value'];
                        }

                        if (
                            in_array($type, ['float', 'double', 'decimal'], true)
                            && is_numeric($setting['value'])
                        ) {
                            $setting['value'] = (float) $setting['value'];
                        }
                    }

                    return $setting;
                },
                $settings,
            ),
        ]);
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $settings = $this->input('settings', []);

            if (! is_array($settings)) {
                return;
            }

            foreach ($settings as $index => $setting) {
                if (! is_array($setting)) {
                    continue;
                }

                $key = $setting['key'] ?? null;
                $type = $setting['type'] ?? null;

                if (
                    ! is_string($key)
                    || ! array_key_exists($key, self::definitions())
                ) {
                    continue;
                }

                $definition = self::definitions()[$key];

                if ($type !== $definition['type']) {
                    $validator->errors()->add(
                        "settings.{$index}.type",
                        "The selected type is invalid for {$key}.",
                    );

                    continue;
                }

                $value = $setting['value'] ?? null;

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
        });
    }
}
