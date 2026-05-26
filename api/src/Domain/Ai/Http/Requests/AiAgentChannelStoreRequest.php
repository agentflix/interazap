<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para criação e atualização de canal de agente de IA.
 */
final class AiAgentChannelStoreRequest extends FormRequest
{
    /** Verifica se o usuário possui permissão para gerenciar autopilots. */
    public function authorize(): bool
    {
        return $this->user()->can('ai.autopilots.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'channel' => ['required', 'string', 'max:40'],
            'external_ref' => ['nullable', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
        ];
    }
}
