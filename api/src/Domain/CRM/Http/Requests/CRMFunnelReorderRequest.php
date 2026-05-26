<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Domain\Shared\Rules\TenantExistsRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para reordenar etapas do funil.
 */
class CRMFunnelReorderRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (string) $user->tenant_id !== '';
    }

    /**
     * Retorna as regras de validação aplicadas à requisição.
     *
     * @return array<string, mixed> Regras de validação.
     */
    public function rules(): array
    {
        return [
            'steps' => ['required', 'array'],
            'steps.*.id' => ['required', 'uuid', new TenantExistsRule('crm_negotiation_funnel_steps')],
            'steps.*.order' => ['required', 'integer', 'min:1'],
        ];
    }
}
