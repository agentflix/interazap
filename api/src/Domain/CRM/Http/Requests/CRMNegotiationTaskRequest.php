<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Domain\Shared\Rules\TenantExistsRule;
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
            'auth_user_id' => ['nullable', 'uuid', new TenantExistsRule('auth_users')],
            'action_type' => ['nullable', 'string', 'max:50'],
            'start_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'reminder_at' => ['nullable', 'date'],
            'add_to_agenda' => ['nullable', 'boolean'],
            'agenda_event_id' => ['nullable', 'uuid', new TenantExistsRule('crm_events')],
            'notify_ui' => ['nullable', 'boolean'],
            'notify_email' => ['nullable', 'boolean'],
            'notify_push' => ['nullable', 'boolean'],
            'notify_whatsapp' => ['nullable', 'boolean'],
            'priority' => ['nullable', 'string', 'in:low,medium,high'],
        ];
    }
}
