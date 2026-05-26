<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para conectar instância Uazapi.
 */
final class PlatformUazapiConnectRequest extends FormRequest
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
     * Regras de validação para iniciar conexão da instância.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'mode' => ['nullable', 'string', 'in:qr,pair'],
            'phone' => ['nullable', 'string', 'regex:/^\\d{10,15}$/'],
        ];
    }
}
