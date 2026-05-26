<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Requests;

use Domain\Platform\Models\PlatformUazapiInstance;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para envio de arquivo/mídia via Uazapi.
 */
class PlatformUazapiSendFileRequest extends FormRequest
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
     * Regras de validação para envio de arquivo via Uazapi.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'number' => ['required', 'string', 'regex:/^[0-9@.\w-]{6,}$/'],
            'url' => ['required', 'url'],
            'caption' => ['nullable', 'string'],
        ];
    }
}
