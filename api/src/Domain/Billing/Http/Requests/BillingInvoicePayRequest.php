<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Requests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\TransientToken;

/**
 * Validação para pagamento de fatura.
 */
final class BillingInvoicePayRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null) {
            return false;
        }

        if ($this->tokenAllows($user, 'billing.invoices.pay')) {
            return true;
        }

        return $user->can('billing.invoices.pay');
    }

    private function tokenAllows(Authenticatable $user, string $permission): bool
    {
        if (! method_exists($user, 'currentAccessToken')) {
            return false;
        }

        $token = $user->currentAccessToken();

        if ($token === null || $token instanceof TransientToken) {
            return false;
        }

        return $user->tokenCan($permission) || $user->tokenCan('*');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'method' => ['required', 'string', Rule::in(['PIX', 'CREDIT_CARD'])],
            'card' => ['required_if:method,CREDIT_CARD', 'array'],
            'card.holder_name' => ['required_if:method,CREDIT_CARD', 'string', 'max:120'],
            'card.number' => ['required_if:method,CREDIT_CARD', 'string', 'max:30'],
            'card.expiry_month' => ['required_if:method,CREDIT_CARD', 'string', 'max:2'],
            'card.expiry_year' => ['required_if:method,CREDIT_CARD', 'string', 'max:4'],
            'card.cvv' => ['required_if:method,CREDIT_CARD', 'string', 'max:4'],
            'holder_info' => ['required_if:method,CREDIT_CARD', 'array'],
            'holder_info.name' => ['required_if:method,CREDIT_CARD', 'string', 'max:120'],
            'holder_info.email' => ['required_if:method,CREDIT_CARD', 'email'],
            'holder_info.cpf_cnpj' => ['required_if:method,CREDIT_CARD', 'string', 'max:20'],
            'holder_info.postal_code' => ['required_if:method,CREDIT_CARD', 'string', 'max:20'],
            'holder_info.address_number' => ['required_if:method,CREDIT_CARD', 'string', 'max:20'],
            'holder_info.phone' => ['required_if:method,CREDIT_CARD', 'string', 'max:20'],
        ];
    }
}
