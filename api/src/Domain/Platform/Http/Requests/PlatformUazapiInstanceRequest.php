<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para criação de instância Uazapi.
 */
final class PlatformUazapiInstanceRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || (string) $user->tenant_id === '') {
            return false;
        }

        return $user->can('whatsapp.manage');
    }

    /**
     * Regras de validação para criação de instância Uazapi.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'system_name' => ['nullable', 'string', 'max:100'],
            'config' => ['nullable', 'array'],
            'config.*' => ['nullable', 'string', 'max:255'],
        ];
    }
}
