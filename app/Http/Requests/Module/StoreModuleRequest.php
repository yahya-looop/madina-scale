<?php

namespace App\Http\Requests\Module;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreModuleRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may create a module.
     */
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->can('system.modules.create');
    }

    /**
     * Get the validation rules for creating a module.
     *
     * System modules are intentionally not accepted from the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',
                Rule::unique('modules', 'slug'),
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'icon' => [
                'nullable',
                'string',
                'max:100',
            ],

            'sort_order' => [
                'sometimes',
                'integer',
                'min:0',
            ],

        ];
    }
}
