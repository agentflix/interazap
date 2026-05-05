<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para criação de fatura na visão de plataforma (admin).
 */
final class PlatformBillingInvoiceStoreRequest extends FormRequest
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
     * Regras de validação para criação de fatura.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'tenant_id' => ['required', 'uuid', 'exists:platform_tenants,id'],
            'plan_id' => ['nullable', 'uuid', 'exists:platform_plans,id'],
            'reference_month' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'amount' => ['required', 'numeric', 'min:0'],
            'due_date' => ['required', 'date'],
            'status' => ['nullable', 'string', 'in:draft,pending'],
        ];
    }
}
