<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Requests;

use Domain\Billing\Enums\BillingInvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação para listagem de faturas na visão de plataforma (admin).
 */
final class PlatformBillingInvoiceIndexRequest extends FormRequest
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

        return $user->isSuperAdmin() || $user->isInquilino();
    }

    /**
     * Regras de validação para filtros de listagem.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $statuses = array_map(
            static fn (BillingInvoiceStatus $status): string => $status->value,
            BillingInvoiceStatus::cases()
        );

        return [
            'tenant_id' => ['nullable', 'uuid'],
            'status' => ['nullable', 'string', Rule::in($statuses)],
            'payment_method' => ['nullable', 'string', Rule::in(['pix', 'credit_card'])],
            'due_date_from' => ['nullable', 'date'],
            'due_date_to' => ['nullable', 'date'],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
