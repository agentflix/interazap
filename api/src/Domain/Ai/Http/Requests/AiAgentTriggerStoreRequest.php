<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest for AI agent trigger storage.
 */
final class AiAgentTriggerStoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
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
            'type' => ['required', 'string', 'max:50'],
            'config' => ['nullable', 'array'],
            'status' => ['sometimes', 'string', 'in:active,inactive'],
        ];
    }
}
