<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para criação de item de negociação.
 */
class CRMNegotiationProductRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'crm_product_id' => ['nullable', 'string', 'exists:crm_products,id'],
        ];
    }
}
