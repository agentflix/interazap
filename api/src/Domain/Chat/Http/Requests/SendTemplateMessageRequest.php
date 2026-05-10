<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Validação para envio de template aprovado em um ticket Meta.
 *
 * Body esperado (use uma das opções):
 *   - template_id:   uuid do template (prioridade)
 *   - template_name: nome do template (alternativa quando UUID não disponível)
 *   - variables:     objeto com chaves "1","2",... ou array posicional (opcional)
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
            'template_id'   => ['nullable', 'uuid', 'exists:chat_message_templates,id'],
            'template_name' => ['nullable', 'string', 'max:255'],
            'variables'     => ['nullable', 'array'],
            'variables.*'   => ['nullable', 'string', 'max:1024'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            if (! $this->filled('template_id') && ! $this->filled('template_name')) {
                $v->errors()->add('template_id', 'Informe template_id ou template_name.');
            }
        });
    }
}
