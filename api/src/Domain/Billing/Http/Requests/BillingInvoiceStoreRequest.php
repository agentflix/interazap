<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Requests;

use Domain\Billing\Enums\BillingInvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação para criação de faturas.
 */
final class BillingInvoiceStoreRequest extends FormRequest
{
    /**
     * Verifica se o usuário está autorizado a criar faturas.
     */
    public function authorize(): bool
    {
        return $this->user()->can('billing.invoices.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $statuses = array_map(
            static fn (BillingInvoiceStatus $status): string => $status->value,
            BillingInvoiceStatus::cases()
        );

        return [
            'plan_id' => ['nullable', 'uuid', 'exists:platform_plans,id'],
            'reference_month' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', 'string', Rule::in($statuses)],
            'due_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', Rule::in(['pix', 'credit_card'])],
        ];
    }
}
