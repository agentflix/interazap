<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request validation for agent skill create/update.
 */
final class AiAgentSkillStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            'metadata' => ['nullable', 'array'],
            'metadata.type' => ['nullable', 'string', 'in:classification,extraction,routing,validation,summarization,custom'],
            'metadata.priority' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }
}
