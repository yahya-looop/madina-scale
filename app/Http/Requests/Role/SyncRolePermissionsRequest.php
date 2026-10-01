<?php

namespace App\Http\Requests\Role;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncRolePermissionsRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may manage
     * permissions assigned to a role.
     */
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->can('system.roles.manage_permissions');
    }

    /**
     * Get the validation rules for synchronizing role permissions.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'permission_ids' => [
                'required',
                'array',
            ],

            'permission_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('permissions', 'id'),
            ],
        ];
    }

    /**
     * Prevent permissions from being synchronized for system roles.
     */
    protected function prepareForValidation(): void
    {
        $role = $this->route('role');

        if ($role instanceof Role && $role->is_system) {
            abort(403);
        }
    }
}
