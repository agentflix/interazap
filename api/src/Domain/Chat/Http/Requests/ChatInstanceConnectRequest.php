<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para Conexão de Instância.
 *
 * Define as regras para solicitação de pareamento de uma instância,
 * suportando os modos de QR Code ou código numérico (pairing code).
 *
 * @category Requests
 */
class ChatInstanceConnectRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     *
     * @return bool True se autorizado.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->can('chat.instances.manage') || $user->can('whatsapp.manage')) {
            return true;
        }

        return (string) $user->tenant_id !== '';
    }

    /**
     * Define as regras de validação que se aplicam à requisição.
     *
     * @return array<string, mixed> Regras de validação.
     */
    public function rules(): array
    {
        return [
            'mode' => ['required', 'in:qr,pair'],
            'phone' => ['nullable', 'string'],
        ];
    }
}
