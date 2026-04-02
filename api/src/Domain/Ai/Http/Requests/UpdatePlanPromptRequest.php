<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para atualização de Plan Prompts.
 */
class UpdatePlanPromptRequest extends FormRequest
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
            'content' => ['required', 'string'],
            'mandatory_rules' => ['nullable', 'array'],
            'mandatory_rules.*.rule' => ['required_with:mandatory_rules', 'string'],
            'mandatory_rules.*.description' => ['required_with:mandatory_rules', 'string'],
            'token_limit_monthly' => ['nullable', 'integer', 'min:0'],
            'allow_overage' => ['sometimes', 'boolean'],
            'overage_price_per_1k' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
