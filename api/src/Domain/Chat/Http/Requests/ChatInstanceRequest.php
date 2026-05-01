<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Domain\Chat\Policies\ChatInstancePolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação para Cadastro/Edição de Instância de Chat.
 *
 * Define as regras para gerenciar instâncias de comunicação, validando o provedor,
 * status de ativação e parâmetros específicos de configuração.
 *
 * @category Requests
 */
class ChatInstanceRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     *
     * @return bool True se autorizado.
     */
    public function authorize(): bool
    {
        $user = $this->user('sanctum') ?? $this->user();

        if ($user === null) {
            return false;
        }

        if ($this->isMethod('post')) {
            $policy = app(ChatInstancePolicy::class);

            return $policy->create($user);
        }

        if ($user->can('chat.instances.manage') || $user->can('whatsapp.manage')) {
            return true;
        }

        return (string) $user->tenant_id !== '';
    }

    /**
     * Define as regras de validação que se aplicam à requisição.
     *
     * @return array<string, mixed> Regras de validação.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'provider' => ['required', 'string', 'in:zapi,uazapi,meta,telegram'],
            'is_active' => ['boolean'],
            'evaluation_enabled' => ['boolean'],
            'evaluation_cutoff_score' => ['integer', 'min:1', 'max:5'],
            'token' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $this->isMethod('post') && $this->input('provider') === 'uazapi'),
            ],
            'bot_token' => [
                'nullable',
                'string',
                'max:255',
                Rule::requiredIf(fn () => $this->input('provider') === 'telegram'),
            ],
            'settings' => ['nullable', 'array'],
            'settings.channel_provider_id' => ['nullable', 'integer'],
            'settings.cellphone' => ['nullable', 'string'],
            'settings.instance' => ['nullable', 'string'],
            'settings.client_token' => ['nullable', 'string'],
            'settings.token' => ['nullable', 'string', 'max:255'],
            'settings.send_attendant_name' => ['nullable', 'boolean'],
            'settings.send_outside_business_hours_message' => ['nullable', 'boolean'],
            'settings.outside_business_hours_message' => ['nullable', 'string', 'max:2000'],
            'settings.send_no_business_hours_message' => ['nullable', 'boolean'],
            'settings.no_business_hours_message' => ['nullable', 'string', 'max:2000'],
            'settings.send_department_transfer_message' => ['nullable', 'boolean'],
            'settings.department_transfer_message' => ['nullable', 'string', 'max:2000'],
            'settings.send_start_service_message' => ['nullable', 'boolean'],
            'settings.start_service_message' => ['nullable', 'string', 'max:2000'],
            'settings.send_end_service_message' => ['nullable', 'boolean'],
            'settings.end_service_message' => ['nullable', 'string', 'max:2000'],
            'settings.channel_fallback_message' => ['nullable', 'string', 'max:2000'],
            'settings.phone_number_id' => ['nullable', 'string', 'max:255'],
            'settings.access_token' => ['nullable', 'string', 'max:500'],

            // Auto-close by inactivity (nullable = herda do tenant)
            'auto_close_enabled' => ['nullable', 'boolean'],
            'auto_close_after_minutes' => ['nullable', 'integer', 'min:1'],
            'auto_close_target' => ['nullable', 'string', Rule::in(['both', 'client', 'agent'])],
            'auto_close_message' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
