<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Valida os dados para upload de arquivo CSV de importação de contatos.
 *
 * @return array<string, mixed> Regras de validação.
 */
final class CRMContactImportUploadRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:csv,txt', 'mimetypes:text/plain,text/csv,application/csv', 'max:51200'],
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
            'file.required' => 'Arquivo CSV é obrigatório.',
            'file.mimes' => 'Formato inválido. Envie um arquivo CSV.',
            'file.mimetypes' => 'Tipo de arquivo inválido. Envie um CSV.',
            'file.max' => 'O arquivo deve ter no máximo 50MB.',
        ];
    }
}
