<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação do payload de upgrade trial → plano pago.
 */
final class BillingUpgradeFromTrialRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'string', 'uuid', 'exists:platform_plans,id'],
            'card_token' => ['required', 'string', 'max:512'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'plan_id.required' => 'O plano é obrigatório.',
            'plan_id.exists' => 'Plano não encontrado.',
            'card_token.required' => 'O token do cartão é obrigatório.',
        ];
    }
}
