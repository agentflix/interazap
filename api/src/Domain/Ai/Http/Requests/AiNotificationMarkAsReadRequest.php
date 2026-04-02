<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para marcar notificações como lidas.
 *
 * @category Requests
 */
final class AiNotificationMarkAsReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user('sanctum') ?? $this->user();

        return $user !== null && (string) $user->tenant_id !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['required', 'uuid'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'É necessário informar os IDs das notificações.',
            'ids.array' => 'Os IDs devem ser um array.',
            'ids.min' => 'É necessário informar pelo menos um ID.',
            'ids.max' => 'O máximo de 100 notificações por requisição.',
            'ids.*.uuid' => 'ID de notificação inválido.',
        ];
    }
}
