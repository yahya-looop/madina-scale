<?php

namespace App\Http\Requests\ActivityLog;

use App\Services\AuthorizationService;
use Illuminate\Foundation\Http\FormRequest;

class ActivityLogIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        return app(AuthorizationService::class)->can(
            $user,
            'system.activity_logs.view',
        );
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'search' => [
                'nullable',
                'string',
                'max:100',
            ],

            'module' => [
                'nullable',
                'string',
                'max:100',
            ],

            'action' => [
                'nullable',
                'string',
                'max:100',
            ],

            'actor_type' => [
                'nullable',
                'in:user,system',
            ],

            'per_page' => [
                'nullable',
                'integer',
                'min:10',
                'max:100',
            ],

            'page' => [
                'nullable',
                'integer',
                'min:1',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'search' => is_string($this->search)
                ? trim($this->search)
                : $this->search,

            'module' => is_string($this->module)
                ? trim($this->module)
                : $this->module,

            'action' => is_string($this->action)
                ? trim($this->action)
                : $this->action,
        ]);
    }
}
