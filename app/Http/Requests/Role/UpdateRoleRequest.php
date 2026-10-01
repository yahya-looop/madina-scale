<?php

namespace App\Http\Requests\Role;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may update this role.
     *
     * Role updates require the dedicated role-management permission.
     */
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->can('system.roles.update');
    }

    /**
     * Get the validation rules for updating a role.
     *
     * System roles are intentionally protected from modification.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $role = $this->route('role');

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
                Rule::unique('roles', 'slug')->ignore(
                    $role instanceof Role ? $role->id : $role,
                ),
            ],

            'description' => [
                'nullable',
                'string',
            ],

            'is_active' => [
                'sometimes',
                'boolean',
            ],
        ];
    }

    /**
     * Prevent system roles from being modified.
     */
    protected function prepareForValidation(): void
    {
        $role = $this->route('role');

        if ($role instanceof Role && $role->is_system) {
            abort(403);
        }
    }
}
