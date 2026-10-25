<?php

namespace App\Http\Requests\Location;

use App\Services\AuthorizationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLocationRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null
            && app(AuthorizationService::class)->can(
                $user,
                'system.locations.create',
            );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'code' => [
                'required',
                'string',
                'max:50',
                'regex:/^[A-Za-z0-9][A-Za-z0-9_-]*$/',
                'unique:locations,code',
            ],

            'type' => [
                'required',
                'string',
                Rule::in([
                    'head_office',
                    'branch',
                    'warehouse',
                    'distribution_hub',
                    'service_center',
                    'factory',
                    'other',
                ]),
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],

            'is_primary' => [
                'sometimes',
                'boolean',
            ],

            'country_code' => [
                'sometimes',
                'string',
                'size:2',
                'regex:/^[A-Z]{2}$/',
            ],

            'division' => [
                'nullable',
                'string',
                'max:120',
            ],

            'district' => [
                'nullable',
                'string',
                'max:120',
            ],

            'postal_code' => [
                'nullable',
                'string',
                'max:20',
            ],

            'address' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'email' => [
                'nullable',
                'email:rfc',
                'max:150',
            ],

            'business_hours' => [
                'nullable',
                'array',
            ],

            'business_hours.*' => [
                'array',
            ],

            'business_hours.*.enabled' => [
                'required',
                'boolean',
            ],

            'business_hours.*.open' => [
                'nullable',
                'date_format:H:i',
            ],

            'business_hours.*.close' => [
                'nullable',
                'date_format:H:i',
            ],

            'metadata' => [
                'nullable',
                'array',
            ],
        ];
    }

    /**
     * Prepare normalized input before validation.
     */
    protected function prepareForValidation(): void
    {
        $countryCode = $this->input('country_code');

        $this->merge([
            'name' => is_string($this->input('name'))
                ? trim($this->input('name'))
                : $this->input('name'),

            'code' => is_string($this->input('code'))
                ? strtoupper(trim($this->input('code')))
                : $this->input('code'),

            'country_code' => is_string($countryCode)
                ? strtoupper(trim($countryCode))
                : ($countryCode ?? 'BD'),
        ]);
    }

    /**
     * Add cross-field business validation.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $countryCode = $this->input('country_code', 'BD');

            if (
                $countryCode === 'BD'
                && filled($this->input('postal_code'))
                && ! preg_match(
                    '/^\d{4}$/',
                    (string) $this->input('postal_code'),
                )
            ) {
                $validator->errors()->add(
                    'postal_code',
                    'Bangladesh postal code must contain exactly 4 digits.',
                );
            }

            $this->validateBusinessHours($validator);
        });
    }

    /**
     * Validate operational hours.
     */
    private function validateBusinessHours(Validator $validator): void
    {
        $hours = $this->input('business_hours');

        if ($hours === null) {
            return;
        }

        $allowedDays = [
            'saturday',
            'sunday',
            'monday',
            'tuesday',
            'wednesday',
            'thursday',
            'friday',
        ];

        foreach ($hours as $day => $schedule) {
            if (! in_array($day, $allowedDays, true)) {
                $validator->errors()->add(
                    "business_hours.{$day}",
                    'Invalid business-hours day.',
                );

                continue;
            }

            if (! is_array($schedule)) {
                continue;
            }

            $enabled = (bool) ($schedule['enabled'] ?? false);

            if (! $enabled) {
                continue;
            }

            $open = $schedule['open'] ?? null;
            $close = $schedule['close'] ?? null;

            if ($open === null || $close === null) {
                $validator->errors()->add(
                    "business_hours.{$day}",
                    'Opening and closing time are required when the day is enabled.',
                );

                continue;
            }

            if ($open >= $close) {
                $validator->errors()->add(
                    "business_hours.{$day}",
                    'Closing time must be later than opening time.',
                );
            }
        }
    }
}
