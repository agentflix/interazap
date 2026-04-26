<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for CRM contact CSV upload.
 */
final class CRMContactImportUploadRequest extends FormRequest
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
            'file' => ['required', 'file', 'mimes:csv,txt', 'mimetypes:text/plain,text/csv,application/csv', 'max:51200'],
        ];
    }

    /**
     * @return array<string, string>
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
