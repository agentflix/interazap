<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para tarefa de negociação.
 */
class CRMNegotiationTaskRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (string) $user->tenant_id !== '';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_date' => ['nullable', 'date'],
            'status' => ['nullable', 'string', 'max:50'],
            'auth_user_id' => ['nullable', 'uuid', 'exists:auth_users,id'],
        ];
    }
}
