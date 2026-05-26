<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para atualização de configuração da instância Uazapi.
 */
final class PlatformUazapiInstanceUpdateAdminFieldsRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && (string) $this->user()->tenant_id !== '';
    }

    /**
     * Regras de validação para atualização de configuração da instância.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'config' => ['required', 'array'],
            'config.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
