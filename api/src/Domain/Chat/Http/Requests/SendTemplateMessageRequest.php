<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para envio de template aprovado em um ticket Meta.
 *
 * Body esperado:
 *   - template_id: uuid (obrigatório)
 *   - variables:   objeto com chaves "1","2",... ou array posicional (opcional)
 */
final class SendTemplateMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (string) $user->tenant_id !== '';
    }

    /**
     * @return array<string, array<int, string>|string>
     */
    public function rules(): array
    {
        return [
            'template_id' => ['required', 'uuid', 'exists:chat_message_templates,id'],
            'variables' => ['nullable', 'array'],
            'variables.*' => ['nullable', 'string', 'max:1024'],
        ];
    }
}
