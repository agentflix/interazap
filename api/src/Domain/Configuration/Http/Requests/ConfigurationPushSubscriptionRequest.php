<?php

declare(strict_types=1);

namespace Domain\Configuration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação do payload de inscrição push web.
 */
final class ConfigurationPushSubscriptionRequest extends FormRequest
{
    /** Verifica se o usuário está autorizado a realizar esta requisição. */
    public function authorize(): bool
    {
        return $this->user() !== null && (string) $this->user()->tenant_id !== '';
    }

    /**
     * Retorna as regras de validação aplicadas à requisição.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'endpoint' => 'required|string|max:1000',
            'keys' => 'required|array',
            'keys.p256dh' => 'required|string|max:255',
            'keys.auth' => 'required|string|max:255',
            'content_encoding' => 'nullable|string|in:aes128gcm,aesgcm',
        ];
    }
}
