<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to create a user.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('system.users.create') ?? false;
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
                'max:255',
            ],

            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],

            'password' => [
                'required',
                'confirmed',
                Password::min(12),
            ],

            'status' => [
                'sometimes',
                'string',
                Rule::in([
                    'active',
                    'inactive',
                ]),
            ],

            'role_ids' => [
                'sometimes',
                'array',
            ],

            'role_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('roles', 'id')->where(
                    fn ($query) => $query
                        ->where('is_system', false)
                        ->where('is_active', true)
                ),
            ],
        ];
    }
}
