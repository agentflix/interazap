<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para atualização de Tenant Prompts.
 */
class UpdateTenantPromptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('ai.prompts.manage');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'content' => ['required', 'string', 'max:10000'],
        ];
    }
}
