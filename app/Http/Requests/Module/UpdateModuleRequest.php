<?php

namespace App\Http\Requests\Module;

use App\Models\Module;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateModuleRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may update a module.
     *
     * Module updates require the dedicated module-management permission.
     */
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->can('system.modules.update');
    }

    /**
     * Get the validation rules for updating a module.
     *
     * System modules are intentionally protected from modification.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $module = $this->route('module');

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
                Rule::unique('modules', 'slug')->ignore(
                    $module instanceof Module ? $module->id : $module,
                ),
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

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Prevent system modules from being modified.
     */
    protected function prepareForValidation(): void
    {
        $module = $this->route('module');

        if ($module instanceof Module && $module->is_system) {
            abort(403);
        }
    }
}
