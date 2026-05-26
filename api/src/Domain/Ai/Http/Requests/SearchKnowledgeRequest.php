<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Domain\Ai\Enums\AiDocumentType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação para busca na base de conhecimento via RAG.
 */
class SearchKnowledgeRequest extends FormRequest
{
    /** Verifica se o usuário possui permissão para visualizar a base de conhecimento. */
    public function authorize(): bool
    {
        return $this->user()->can('ai.knowledge.view');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'query' => [
                'required',
                'string',
                'min:3',
                'max:1000',
            ],
            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:20',
            ],
            'min_score' => [
                'nullable',
                'numeric',
                'min:0',
                'max:1',
            ],
            'mode' => [
                'nullable',
                'string',
                'in:vector,hybrid',
            ],
            'document_ids' => [
                'nullable',
                'array',
            ],
            'document_ids.*' => [
                'uuid',
                Rule::exists('ai_knowledge_documents', 'id')->where(function ($query): void {
                    $query->where('tenant_id', $this->user()?->tenant_id);
                }),
            ],
            'file_types' => [
                'nullable',
                'array',
            ],
            'file_types.*' => [
                'string',
                Rule::in(AiDocumentType::values()),
            ],
            'created_after' => [
                'nullable',
                'date_format:Y-m-d\TH:i:sP',
            ],
            'created_before' => [
                'nullable',
                'date_format:Y-m-d\TH:i:sP',
            ],
        ];
    }

    /**
     * Retorna mensagens de erro personalizadas para o validador.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'query.required' => 'A search query is required.',
            'query.min' => 'The search query must be at least 3 characters.',
            'query.max' => 'The search query may not exceed 1000 characters.',
            'limit.min' => 'The limit must be at least 1.',
            'limit.max' => 'The limit may not exceed 20.',
            'min_score.min' => 'The min_score must be at least 0.',
            'min_score.max' => 'The min_score may not exceed 1.',
        ];
    }
}
