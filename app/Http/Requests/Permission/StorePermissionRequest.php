<?php

namespace App\Http\Requests\Permission;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePermissionRequest extends FormRequest
{
    /**
     * Determine whether the authenticated user may create permissions.
     */
    public function authorize(): bool
    {
        return $this->user() !== null
            && $this->user()->can('system.permissions.create');
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'module_id' => [
                'required',
                'integer',
                Rule::exists('modules', 'id')
                    ->where(fn ($query) => $query->where('is_active', true)),
            ],

            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('permissions', 'name')
                    ->where(
                        fn ($query) => $query->where(
                            'module_id',
                            $this->input('module_id'),
                        ),
                    ),
            ],

            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:[._-][a-z0-9]+)*$/',
                Rule::unique('permissions', 'slug'),
            ],

            'description' => [
                'nullable',
                'string',
            ],
        ];
    }
}
