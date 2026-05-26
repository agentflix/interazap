<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Valida os dados para importação de contatos do CRM via CSV.
 *
 * @return array<string, mixed> Regras de validação.
 */
final class CRMContactImportRequest extends FormRequest
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
            'import_id' => ['required', 'string'],
            'delimiter' => ['sometimes', 'string', Rule::in([',', ';'])],
            'has_header' => ['sometimes', 'boolean'],
            'instance_id' => ['nullable', 'string'],
            'mapping' => ['required', 'array'],
            'mapping.name' => ['required', 'string'],
            'mapping.number' => ['required', 'string'],
            'mapping.email' => ['nullable', 'string'],
            'mapping.company' => ['nullable', 'string'],
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
            'import_id.required' => 'Identificador de importação é obrigatório.',
            'mapping.name.required' => 'Mapeamento do campo Nome é obrigatório.',
            'mapping.number.required' => 'Mapeamento do campo Número é obrigatório.',
            'delimiter.in' => 'Delimitador inválido. Use vírgula ou ponto e vírgula.',
        ];
    }
}
