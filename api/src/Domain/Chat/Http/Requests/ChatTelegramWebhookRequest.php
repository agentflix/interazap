<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Domain\Chat\Models\ChatInstance;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para Webhook Telegram.
 *
 * Define as regras para recebimento do payload enviado pelo Gateway
 * a partir de updates da Telegram Bot API, garantindo que os campos
 * mínimos para identificação do evento estejam presentes.
 *
 * @category Requests
 */
class ChatTelegramWebhookRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     *
     * Verifica se o token corresponde a uma instância Telegram ativa.
     */
    public function authorize(): bool
    {
        $token = $this->route('token');

        if (! is_string($token) || $token === '') {
            return false;
        }

        return ChatInstance::query()
            ->where('provider', 'telegram')
            ->where('webhook_token', $token)
            ->exists();
    }

    /**
     * Define as regras de validação que se aplicam à requisição.
     *
     * Telegram updates podem ser: message, edited_message, callback_query, etc.
     * Aceitamos o payload flexível pois a normalização ocorre na Action.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'update_id' => ['required', 'integer'],
            'message' => ['sometimes', 'array'],
            'message.message_id' => ['sometimes', 'integer'],
            'message.chat' => ['sometimes', 'array'],
            'message.chat.id' => ['sometimes', 'integer'],
            'message.chat.type' => ['sometimes', 'string'],
            'message.from' => ['sometimes', 'array'],
            'message.from.id' => ['sometimes', 'integer'],
            'message.from.first_name' => ['sometimes', 'string', 'max:255'],
            'message.from.last_name' => ['sometimes', 'string', 'max:255'],
            'message.from.username' => ['sometimes', 'string', 'max:255'],
            'message.text' => ['sometimes', 'string', 'max:65535'],
            'message.date' => ['sometimes', 'integer'],
            'message.photo' => ['sometimes', 'array'],
            'message.video' => ['sometimes', 'array'],
            'message.audio' => ['sometimes', 'array'],
            'message.document' => ['sometimes', 'array'],
            'message.sticker' => ['sometimes', 'array'],
            'message.voice' => ['sometimes', 'array'],
            'message.location' => ['sometimes', 'array'],
            'message.caption' => ['sometimes', 'string', 'max:65535'],
            'message.reply_to_message' => ['sometimes', 'array'],
            'edited_message' => ['sometimes', 'array'],
            'edited_message.message_id' => ['sometimes', 'integer'],
            'edited_message.chat' => ['sometimes', 'array'],
            'edited_message.chat.id' => ['sometimes', 'integer'],
            'edited_message.from' => ['sometimes', 'array'],
            'edited_message.text' => ['sometimes', 'string', 'max:65535'],
            'edited_message.edit_date' => ['sometimes', 'integer'],
            'callback_query' => ['sometimes', 'array'],
        ];
    }
}
