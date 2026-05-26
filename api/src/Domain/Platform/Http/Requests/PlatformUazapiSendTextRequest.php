<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Requests;

use Domain\Platform\Models\PlatformUazapiInstance;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para envio de mensagem de texto via Uazapi.
 */
class PlatformUazapiSendTextRequest extends FormRequest
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

        $token = $this->route('token');
        if (! is_string($token) || $token === '') {
            return false;
        }

        $instance = PlatformUazapiInstance::query()
            ->where('token', $token)
            ->first();

        if ($instance === null) {
            return true;
        }

        return $instance->tenant_id === $user->tenant_id;
    }

    /**
     * Regras de validação para envio de mensagem de texto via Uazapi.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'regex:/^[0-9@.\w-]{6,}$/'],
            'text' => ['required', 'string'],
            'linkPreview' => ['nullable', 'boolean'],
            'linkPreviewTitle' => ['nullable', 'string'],
            'linkPreviewDescription' => ['nullable', 'string'],
            'linkPreviewImage' => ['nullable', 'string'],
            'linkPreviewLarge' => ['nullable', 'boolean'],
            'replyid' => ['nullable', 'string'],
            'mentions' => ['nullable', 'string'],
        ];
    }
}
