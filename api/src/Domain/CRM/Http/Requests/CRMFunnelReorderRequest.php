<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para reordenar etapas do funil.
 */
class CRMFunnelReorderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (string) $user->tenant_id !== '';
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'steps' => ['required', 'array'],
            'steps.*.id' => ['required', 'uuid', 'exists:crm_negotiation_funnel_steps,id'],
            'steps.*.order' => ['required', 'integer', 'min:1'],
        ];
    }
}
