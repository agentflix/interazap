<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Domain\Shared\Rules\TenantExistsRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para proposta e seus itens.
 */
class CRMProposalRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'number' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', 'string', 'in:draft,sent,accepted,rejected'],
            'valid_until' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.name' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['nullable', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
            'items.*.position' => ['nullable', 'integer', 'min:1'],
            'items.*.crm_product_id' => ['nullable', 'uuid', new TenantExistsRule('crm_products')],
        ];
    }
}
