<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação para motivo de perda.
 */
class CRMReasonLossRequest extends FormRequest
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
     * @return array<string, array<int, mixed>> Regras de validação.
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        $reasonId = $this->route('id');

        $uniqueNameRule = Rule::unique('crm_reason_losses', 'name')
            ->where(fn ($query) => $query->where('tenant_id', $tenantId));

        if (is_string($reasonId) && $reasonId !== '') {
            $uniqueNameRule = $uniqueNameRule->ignore($reasonId, 'id');
        }

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                $uniqueNameRule,
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'requires_comment' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Retorna mensagens de erro personalizadas para as regras de validação.
     *
     * @return array<string, string> Mensagens customizadas.
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Este motivo de perda já existe para sua empresa.',
            'name.required' => 'O nome do motivo de perda é obrigatório.',
        ];
    }
}
