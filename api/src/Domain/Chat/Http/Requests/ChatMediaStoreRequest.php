<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Domain\Platform\Exceptions\PlanLimitExceededException;
use Domain\Platform\Services\PlatformPlanEnforcementService;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para upload de mídia do chat.
 *
 * @category Requests
 */
final class ChatMediaStoreRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        $user = $this->user('sanctum') ?? $this->user();
        if (! $user) {
            return false;
        }

        $file = $this->file('file');
        $size = $file ? (int) $file->getSize() : 0;

        $enforcement = app(PlatformPlanEnforcementService::class);
        if (! $enforcement->canUploadFile((string) $user->tenant_id, $size)) {
            throw PlanLimitExceededException::forResource('storage');
        }

        if ($user->can('chat.medias.manage') || $user->can('chat.messages.manage') || $user->can('whatsapp.manage')) {
            return true;
        }

        return (string) $user->tenant_id !== '';
    }

    /**
     * Regras de validação.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:5120'],
        ];
    }

    /**
     * Mensagens de erro personalizadas.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.required' => 'O arquivo é obrigatório.',
            'file.max' => 'O arquivo deve ter no máximo 5MB.',
        ];
    }
}
