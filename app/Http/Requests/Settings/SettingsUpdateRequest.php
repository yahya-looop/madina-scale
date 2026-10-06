<?php

namespace App\Http\Requests\Settings;

use App\Services\AuthorizationService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SettingsUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to update system settings.
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
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
                'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',
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

    /**
     * Prepare validated setting values.
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
                        array_key_exists('value', $setting)
                        && is_string($setting['value'])
                    ) {
                        $type = $setting['type'] ?? 'string';

                        if (in_array(
                            $type,
                            ['boolean', 'bool'],
                            true,
                        )) {
                            $setting['value'] = filter_var(
                                $setting['value'],
                                FILTER_VALIDATE_BOOLEAN,
                                FILTER_NULL_ON_FAILURE,
                            );
                        }

                        if (
                            in_array(
                                $type,
                                ['integer', 'int'],
                                true,
                            )
                            && is_numeric($setting['value'])
                        ) {
                            $setting['value'] = (int) $setting['value'];
                        }

                        if (
                            in_array(
                                $type,
                                ['float', 'double', 'decimal'],
                                true,
                            )
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
}
