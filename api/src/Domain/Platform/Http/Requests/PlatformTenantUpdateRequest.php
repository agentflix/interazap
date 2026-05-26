<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Requests;

use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Validação para atualização de tenant da plataforma.
 */
final class PlatformTenantUpdateRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user === null) {
            return false;
        }

        $tenantId = (string) $this->route('id');
        $tenant = PlatformTenant::query()->findOrFail($tenantId);

        return Gate::forUser($user)->check('update', $tenant);
    }

    /**
     * Prepara e normaliza os dados antes da validação (estado em maiúsculas).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('state')) {
            $this->merge([
                'state' => strtoupper((string) $this->input('state')),
            ]);
        }
    }

    /**
     * Regras de validação para atualização de tenant.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (string) $this->route('id');

        return [
            'name' => ['required', 'string', 'max:255'],
            'tenant_code' => ['nullable', 'string', 'max:12', Rule::unique('platform_tenants', 'tenant_code')->ignore($tenantId)],
            'email' => ['nullable', 'email', 'max:255'],
            'document' => ['nullable', 'string', 'max:32'],
            'is_active' => ['sometimes', 'boolean'],
            'segment_id' => ['nullable', 'uuid', 'exists:ai_prompt_segments,id'],
            'plan_id' => ['required', 'uuid', 'exists:platform_plans,id'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'street' => ['nullable', 'string', 'max:255'],
            'number' => ['nullable', 'string', 'max:20'],
            'complement' => ['nullable', 'string', 'max:120'],
            'district' => ['nullable', 'string', 'max:120'],
            'city' => ['nullable', 'string', 'max:120'],
            'state' => ['nullable', 'string', 'size:2', Rule::in(['AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA', 'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN', 'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO'])],
            'zip' => ['nullable', 'string', 'max:20'],
            'zip_code' => ['nullable', 'string', 'max:20'],
            'zipcode' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Mensagens customizadas para erros de validação.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_id.required' => 'O plano é obrigatório.',
        ];
    }
}
