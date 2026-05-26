<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para publicar status de presença da instância de Chat.
 *
 * available: instância aparece como online
 * unavailable: instância aparece como offline
 */
final class ChatInstancePresenceRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        return $this->user() !== null && (string) $this->user()->tenant_id !== '';
    }

    /**
     * Define as regras de validação que se aplicam à requisição.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'presence' => ['required', 'string', 'in:available,unavailable'],
        ];
    }
}
