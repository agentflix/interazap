<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para envio de localização.
 *
 * @category Requests
 */
final class ChatMessageLocationRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (string) $user->tenant_id !== '';
    }

    /**
     * Regras de validação.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'location' => ['required', 'array'],
            'location.latitude' => ['required', 'numeric', 'between:-90,90'],
            'location.longitude' => ['required', 'numeric', 'between:-180,180'],
            'location.name' => ['nullable', 'string', 'max:255'],
            'location.address' => ['nullable', 'string', 'max:500'],
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
            'location.required' => 'Os dados de localização são obrigatórios.',
            'location.latitude.required' => 'Latitude é obrigatória.',
            'location.longitude.required' => 'Longitude é obrigatória.',
        ];
    }
}
