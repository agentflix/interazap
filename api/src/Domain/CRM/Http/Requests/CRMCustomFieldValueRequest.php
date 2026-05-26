<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Domain\Shared\Rules\TenantExistsRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para valor de campo personalizado.
 */
class CRMCustomFieldValueRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        return $this->user()->can('crm.customs.manage');
    }

    /**
     * Retorna as regras de validação aplicadas à requisição.
     *
     * @return array<string, mixed> Regras de validação.
     */
    public function rules(): array
    {
        return [
            'crm_custom_field_id' => ['required', 'uuid', new TenantExistsRule('crm_custom_fields')],
            'value' => ['nullable'],
        ];
    }
}
