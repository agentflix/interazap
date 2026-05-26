<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para criação de master prompts.
 */
class StoreMasterPromptRequest extends FormRequest
{
    /** Verifica se o usuário possui permissão para gerenciar prompts. */
    public function authorize(): bool
    {
        return $this->user()->can('ai.prompts.manage'); // Autorização será feita via Policy
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
