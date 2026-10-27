<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('system.users.create') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['sometimes', 'nullable', 'string', 'max:100'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users', 'email')],
            'password' => ['required', 'confirmed', Password::min(12)],
            'status' => ['sometimes', 'string', Rule::in(['active', 'inactive'])],
            'role_ids' => ['sometimes', 'array'],
            'location_assignments' => ['sometimes', 'array', 'max:100'],
            'location_assignments.*.location_id' => [
                'required', 'integer', 'distinct',
                Rule::exists('locations', 'id')->where(fn ($query) => $query->where('is_active', true)),
            ],
            'location_assignments.*.access_level' => ['required', Rule::in(['view', 'receive', 'manage'])],
            'role_ids.*' => [
                'integer', 'distinct',
                Rule::exists('roles', 'id')->where(fn ($query) => $query->where('is_system', false)->where('is_active', true)),
            ],
        ];
    }
}
