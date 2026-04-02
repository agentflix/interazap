<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para preview de mensagem de campanha.
 *
 * @category Requests
 */
final class ChatCampaignPreviewRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user('sanctum') ?? $this->user();

        if ($user === null) {
            return false;
        }

        // User must have campaign permission and valid tenant
        if ($user->can('chat.campaigns.manage')) {
            return true;
        }

        return (string) $user->tenant_id !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message' => ['required', 'string', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'message.required' => 'A mensagem é obrigatória para preview.',
            'message.max' => 'A mensagem deve ter no máximo 4096 caracteres.',
        ];
    }
}
