<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para exclusão em lote de documentos da base de conhecimento.
 */
final class AiKnowledgeBulkDeleteRequest extends FormRequest
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
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'uuid'],
        ];
    }
}
