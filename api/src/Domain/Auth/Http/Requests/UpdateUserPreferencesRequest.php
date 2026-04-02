<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Requests;

use Domain\CRM\Enums\CRMTaskStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação para atualização de preferências do usuário.
 *
 * Permite deep merge parcial — campos não enviados são ignorados
 * na validação e preservados no banco pelo Action.
 */
final class UpdateUserPreferencesRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Appearance
            'appearance' => ['sometimes', 'array'],
            'appearance.theme' => ['sometimes', 'string', Rule::in(['light', 'dark', 'system'])],
            'appearance.density' => ['sometimes', 'string', Rule::in(['compact', 'normal', 'expanded'])],
            'appearance.fontSize' => ['sometimes', 'string', Rule::in(['small', 'medium', 'large'])],

            // Behavior
            'behavior' => ['sometimes', 'array'],
            'behavior.sound' => ['sometimes', 'boolean'],
            'behavior.chatNotify' => ['sometimes', 'boolean'],
            'behavior.quickReply' => ['sometimes', 'boolean'],
            'behavior.confirmBulk' => ['sometimes', 'boolean'],
            'behavior.ticketOpenMode' => ['sometimes', 'string', Rule::in(['modal', 'page'])],

            // CRM Defaults
            'crmDefaults' => ['sometimes', 'array'],
            'crmDefaults.negotiationType' => ['sometimes', 'string', Rule::in(['basic', 'advanced', 'full'])],
            'crmDefaults.taskStatus' => ['sometimes', 'string', Rule::enum(CRMTaskStatus::class)],
            'crmDefaults.pipelineView' => ['sometimes', 'string', Rule::in(['kanban', 'list'])],
            'crmDefaults.negotiationOrder' => ['sometimes', 'string', Rule::in(['date', 'value', 'probability'])],

            // Security
            'security' => ['sometimes', 'array'],
            'security.sessionTimeout' => ['sometimes', 'nullable', 'integer', 'min:1'],

            // Accessibility
            'accessibility' => ['sometimes', 'array'],
            'accessibility.highContrast' => ['sometimes', 'boolean'],
            'accessibility.reducedMotion' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert string booleans from HTML forms
        $this->replace(
            array_map(
                fn ($value) => is_string($value) && $value === 'true' ? true : (is_string($value) && $value === 'false' ? false : $value),
                $this->all()
            )
        );
    }
}
