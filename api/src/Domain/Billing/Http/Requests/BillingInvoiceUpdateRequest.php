<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Requests;

use Domain\Billing\Enums\BillingInvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação para atualização de faturas.
 */
final class BillingInvoiceUpdateRequest extends FormRequest
{
    /**
     * Verifica se o usuário está autorizado a editar faturas.
     */
    public function authorize(): bool
    {
        return $this->user()->can('billing.invoices.update');
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
            'plan_id' => ['sometimes', 'nullable', 'uuid', 'exists:platform_plans,id'],
            'reference_month' => ['sometimes', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'string', Rule::in($statuses)],
            'due_date' => ['sometimes', 'date'],
            'payment_method' => ['sometimes', 'nullable', 'string', Rule::in(['pix', 'credit_card'])],
        ];
    }
}
