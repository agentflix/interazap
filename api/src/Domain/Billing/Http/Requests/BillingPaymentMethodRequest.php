<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class BillingPaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'card_token' => ['required', 'string', 'max:512'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'card_token.required' => 'O token do cartão é obrigatório.',
        ];
    }
}
