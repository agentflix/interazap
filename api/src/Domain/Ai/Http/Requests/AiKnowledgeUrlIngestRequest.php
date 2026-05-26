<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para ingestão de URL na base de conhecimento de IA.
 */
final class AiKnowledgeUrlIngestRequest extends FormRequest
{
    /** Verifica se o usuário possui permissão para gerenciar a base de conhecimento. */
    public function authorize(): bool
    {
        return $this->user()->can('ai.knowledge.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2048'],
            'title' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }
}
