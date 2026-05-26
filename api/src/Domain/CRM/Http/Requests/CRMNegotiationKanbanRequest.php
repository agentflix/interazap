<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Domain\CRM\Policies\CRMNegotiationPolicy;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação para consulta do kanban de negociações.
 */
class CRMNegotiationKanbanRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        $user = $this->user('sanctum');
        if (! $user) {
            return false;
        }

        $policy = app(CRMNegotiationPolicy::class);

        return $policy->viewAny($user);
    }

    /**
     * Retorna as regras de validação aplicadas à requisição.
     *
     * @return array<string, mixed> Regras de validação.
     */
    public function rules(): array
    {
        $tenantId = (string) ($this->user('sanctum')->tenant_id ?? '');

        return [
            'funnel_id' => [
                'required',
                'uuid',
                Rule::exists('crm_negotiation_funnels', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'status' => ['nullable', 'string', 'in:open,won,lost'],
            'search' => ['nullable', 'string', 'max:255'],
            'step_id' => [
                'nullable',
                'uuid',
                Rule::exists('crm_negotiation_funnel_steps', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'contact_id' => [
                'nullable',
                'uuid',
                Rule::exists('crm_contacts', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'crm_company_id' => [
                'nullable',
                'uuid',
                Rule::exists('crm_companies', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'user_id' => [
                'nullable',
                'uuid',
                Rule::exists('auth_users', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'expected_close_from' => ['nullable', 'date'],
            'expected_close_to' => ['nullable', 'date', 'after_or_equal:expected_close_from'],
            'amount_min' => ['nullable', 'numeric', 'min:0'],
            'amount_max' => ['nullable', 'numeric', 'gte:amount_min'],
            'lead_score_min' => ['nullable', 'integer', 'between:0,100'],
            'lead_score_max' => ['nullable', 'integer', 'between:0,100', 'gte:lead_score_min'],
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => [
                'uuid',
                Rule::exists('crm_tags', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'reason_loss_id' => [
                'nullable',
                'uuid',
                Rule::exists('crm_reason_losses', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'has_pending_tasks' => ['nullable', Rule::in(['true', 'false', '1', '0'])],
            'product_id' => [
                'nullable',
                'uuid',
                Rule::exists('crm_products', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
        ];
    }
}
