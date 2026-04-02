<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request para atualização de Segment Prompts.
 */
class UpdateSegmentPromptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('ai.prompts.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $routeParam = $this->route('segment');
        $segmentId = is_object($routeParam) && property_exists($routeParam, 'id')
            ? $routeParam->id
            : $routeParam;

        return [
            'code' => ['required', 'string', 'max:50', Rule::unique('ai_prompt_segments', 'code')->ignore($segmentId)],
            'name' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'master_id' => ['nullable', 'uuid', 'exists:ai_prompt_masters,id'],
            'description' => ['nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
