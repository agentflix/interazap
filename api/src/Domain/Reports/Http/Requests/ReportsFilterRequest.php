<?php

declare(strict_types=1);

namespace Domain\Reports\Http\Requests;

use Domain\Shared\Rules\TenantExistsRule;
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
            'user_id' => ['nullable', 'uuid', new TenantExistsRule('auth_users')],
            'funnel_id' => ['nullable', 'uuid', new TenantExistsRule('crm_negotiation_funnels')],
            'step_id' => ['nullable', 'uuid', new TenantExistsRule('crm_negotiation_funnel_steps')],
            'channel' => ['nullable', 'string', 'in:whatsapp,telegram,webchat'],
            'instance_id' => ['nullable', 'uuid', new TenantExistsRule('chat_instances')],
            'reason_loss_id' => ['nullable', 'uuid', new TenantExistsRule('crm_reason_losses')],
            'product_id' => ['nullable', 'uuid', new TenantExistsRule('crm_products')],
            'export_format' => ['nullable', 'string', 'in:json,csv,xlsx'],
        ];
    }
}
