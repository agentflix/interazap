<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Requests;

use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Validação para criação de tenant da plataforma.
 */
final class PlatformTenantStoreRequest extends FormRequest
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

        return Gate::forUser($user)->check('create', PlatformTenant::class);
    }

    /**
     * Prepara e normaliza os dados antes da validação (estado em maiúsculas, telefone formatado).
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('state')) {
            $this->merge([
                'state' => strtoupper((string) $this->input('state')),
            ]);
        }

        if ($phone = $this->input('phone')) {
            $digits = preg_replace('/\D/', '', (string) $phone);
            $formatted = match (strlen($digits)) {
                11 => sprintf('(%s)%s-%s', substr($digits, 0, 2), substr($digits, 2, 5), substr($digits, 7)),
                10 => sprintf('(%s)%s-%s', substr($digits, 0, 2), substr($digits, 2, 4), substr($digits, 6)),
                default => $phone,
            };
            $this->merge(['phone' => $formatted]);
        }
    }

    /**
     * Regras de validação para criação de tenant.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'tenant_code' => ['nullable', 'string', 'max:12', Rule::unique('platform_tenants', 'tenant_code')],
            'email' => ['nullable', 'email', 'max:255'],
            'document' => ['nullable', 'cnpj'],
            'is_active' => ['sometimes', 'boolean'],
            'segment_id' => ['required', 'uuid', 'exists:ai_prompt_segments,id'],
            'plan_id' => ['required', 'uuid', 'exists:platform_plans,id'],
            'phone' => ['nullable', 'celular_com_ddd'],
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
}
