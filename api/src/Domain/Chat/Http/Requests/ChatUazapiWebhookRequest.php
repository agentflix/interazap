<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Domain\Chat\Models\ChatInstance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação para Webhook Uazapi.
 *
 * Define as regras para recebimento do payload bruto enviado pelo gateway Uazapi,
 * garantindo que os campos mínimos para identificação do evento estejam presentes.
 *
 * @category Requests
 */
class ChatUazapiWebhookRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     *
     * @return bool True se autorizado.
     */
    public function authorize(): bool
    {
        $token = $this->route('token');

        if (! is_string($token) || $token === '') {
            return false;
        }

        return ChatInstance::query()
            ->where('provider', 'uazapi')
            ->where('webhook_token', $token)
            ->exists();
    }

    /**
     * Define as regras de validação que se aplicam à requisição.
     *
     * @return array<string, mixed> Regras de validação.
     */
    public function rules(): array
    {
        $isMessageEvent = fn (): bool => strtolower((string) $this->input('EventType')) === 'messages';

        $rules = [
            'EventType' => ['required', 'string'],
            'message' => ['array', Rule::requiredIf($isMessageEvent)],
            'message.id' => ['string', 'max:255', Rule::requiredIf($isMessageEvent)],
            'message.chatid' => ['string', 'max:255', Rule::requiredIf($isMessageEvent)],
            'message.timestamp' => ['sometimes', 'integer'],
            'chat' => ['nullable', 'array'],
            'instance' => ['nullable', 'array'],
            'instanceName' => ['nullable', 'string'],
            'BaseUrl' => ['nullable', 'string'],
            'owner' => ['nullable', 'string'],
            'token' => ['nullable', 'string'],
        ];

        return array_merge($rules, [
            'message.body' => ['sometimes', 'string', 'max:65535'],
            'message.text' => ['sometimes', 'string', 'max:65535'],
            'message.type' => ['sometimes', 'string', 'in:text,image,audio,video,document,sticker'],
            'message.fromMe' => ['sometimes', 'boolean'],
            'chat.id' => ['sometimes', 'string', 'max:255'],
            'chat.name' => ['sometimes', 'string', 'max:255'],
            'instance.id' => ['sometimes', 'string', 'max:255'],
            'raw' => ['sometimes', 'array'],
        ]);
    }
}
