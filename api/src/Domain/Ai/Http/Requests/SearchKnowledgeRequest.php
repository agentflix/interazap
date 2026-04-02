<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for RAG search.
 */
class SearchKnowledgeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('ai.knowledge.view');
    }

    /**
     * Get the validation rules that apply to the request.
     *
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
        ];
    }

    /**
     * Get custom messages for validator errors.
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
