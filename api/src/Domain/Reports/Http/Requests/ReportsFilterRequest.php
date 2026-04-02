<?php

declare(strict_types=1);

namespace Domain\Reports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação de filtros para relatórios.
 */
final class ReportsFilterRequest extends FormRequest
{
    /**
     * Verifica se o usuário está autenticado com tenant.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (string) $user->tenant_id !== '';
    }

    /**
     * Regras de validação dos filtros.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'granularity' => ['nullable', 'string', 'in:day,week,month'],
            'user_id' => ['nullable', 'uuid', 'exists:auth_users,id'],
            'funnel_id' => ['nullable', 'uuid', 'exists:crm_negotiation_funnels,id'],
            'step_id' => ['nullable', 'uuid', 'exists:crm_negotiation_funnel_steps,id'],
            'channel' => ['nullable', 'string', 'in:whatsapp,telegram,webchat'],
            'instance_id' => ['nullable', 'uuid', 'exists:chat_instances,id'],
            'reason_loss_id' => ['nullable', 'uuid', 'exists:crm_reason_losses,id'],
            'product_id' => ['nullable', 'uuid', 'exists:crm_products,id'],
            'export_format' => ['nullable', 'string', 'in:json,csv,xlsx'],
        ];
    }
}
