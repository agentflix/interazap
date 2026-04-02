<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Requests;

use Domain\Billing\Enums\BillingInvoiceStatus;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\TransientToken;

/**
 * Request para listagem de faturas com filtros.
 */
class BillingInvoiceIndexRequest extends FormRequest
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

        if ($this->tokenAllows($user, 'billing.invoices.view')) {
            return true;
        }

        return $user->can('billing.invoices.view');
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
        $statuses = array_map(
            static fn (BillingInvoiceStatus $status): string => $status->value,
            BillingInvoiceStatus::cases()
        );

        return [
            'search' => ['nullable', 'string', 'max:50'],
            'status' => ['nullable', 'string', Rule::in($statuses)],
            'reference_month' => ['nullable', 'string', 'max:7'],
            'due_date_from' => ['nullable', 'date'],
            'due_date_to' => ['nullable', 'date'],
            'payment_method' => ['nullable', 'string', Rule::in(['pix', 'credit_card'])],
            'tenant_id' => ['nullable', 'uuid'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
