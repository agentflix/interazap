<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for CRM contact import.
 */
final class CRMContactImportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (string) $user->tenant_id !== '';
    }

    /**
     * @return array<string, mixed>
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
     * @return array<string, string>
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
