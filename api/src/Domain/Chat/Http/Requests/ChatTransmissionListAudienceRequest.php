<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para contagem de audiência de lista de transmissão.
 *
 * @category Requests
 */
final class ChatTransmissionListAudienceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user('sanctum') ?? $this->user();

        if ($user === null) {
            return false;
        }

        if ($user->can('chat.transmission_lists.manage')) {
            return true;
        }

        return (string) $user->tenant_id !== '';
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'criteria' => ['nullable', 'array'],
            'criteria.tags' => ['nullable', 'array'],
            'criteria.tags.*' => ['uuid'],
            'criteria.funnel_steps' => ['nullable', 'array'],
            'criteria.funnel_steps.*' => ['uuid'],
            'criteria.status' => ['nullable', 'string', 'in:active,inactive,all'],
            'criteria.date_from' => ['nullable', 'date'],
            'criteria.date_to' => ['nullable', 'date', 'after_or_equal:criteria.date_from'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'criteria.tags.*.uuid' => 'ID de tag inválido.',
            'criteria.funnel_steps.*.uuid' => 'ID de etapa do funil inválido.',
            'criteria.date_to.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
        ];
    }
}
