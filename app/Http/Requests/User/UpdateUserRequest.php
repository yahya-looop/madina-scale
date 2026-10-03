<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine whether the user is authorized to update a user.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('system.users.update') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $user = $this->route('user');

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
                Rule::unique('users', 'email')->ignore(
                    is_object($user) ? $user->id : $user
                ),
            ],

            'password' => [
                'nullable',
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
