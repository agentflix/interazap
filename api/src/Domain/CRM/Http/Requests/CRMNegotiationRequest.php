<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Domain\CRM\Policies\CRMNegotiationPolicy;
use Domain\Shared\Rules\TenantExistsRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação para negociação.
 */
class CRMNegotiationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user('sanctum');
        if (! $user) {
            return false;
        }

        if ($this->isMethod('post')) {
            $policy = app(CRMNegotiationPolicy::class);

            return $policy->create($user);
        }

        return (string) $user->tenant_id !== '';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) ($this->user('sanctum')->tenant_id ?? '');

        return [
            'title' => ['required', 'string', 'max:255'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'crm_negotiation_funnel_id' => [
                'required',
                'uuid',
                Rule::exists('crm_negotiation_funnels', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'crm_negotiation_funnel_step_id' => [
                'required',
                'uuid',
                Rule::exists('crm_negotiation_funnel_steps', 'id')->where(
                    fn ($query) => $query
                        ->where('tenant_id', $tenantId)
                        ->where('crm_negotiation_funnel_id', (string) $this->input('crm_negotiation_funnel_id'))
                ),
            ],
            'crm_company_id' => ['nullable', 'uuid', new TenantExistsRule('crm_companies')],
            'crm_contact_id' => [
                'required',
                'uuid',
                Rule::exists('crm_contacts', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                ),
            ],
            'auth_user_id' => ['nullable', 'uuid', new TenantExistsRule('auth_users')],
            'crm_reason_loss_id' => ['nullable', 'uuid', new TenantExistsRule('crm_reason_losses'), 'required_if:status,lost'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'status' => ['nullable', 'string', 'in:open,won,lost'],
            'position' => ['nullable', 'integer', 'min:1'],
            'expected_close' => ['nullable', 'date'],
        ];
    }
}
