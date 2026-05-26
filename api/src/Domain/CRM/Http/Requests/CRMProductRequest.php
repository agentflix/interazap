<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validação para produto/serviço do CRM.
 */
class CRMProductRequest extends FormRequest
{
    /**
     * Determina se o usuário está autorizado a fazer esta requisição.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (string) $user->tenant_id !== '';
    }

    /**
     * Retorna as regras de validação aplicadas à requisição.
     *
     * @return array<string, array<int, mixed>> Regras de validação.
     */
    public function rules(): array
    {
        $tenantId = $this->user()?->tenant_id;
        $productId = $this->route('id');

        $uniqueCodeRule = Rule::unique('crm_products', 'code')
            ->where(fn ($query) => $query->where('tenant_id', $tenantId));

        if (is_string($productId) && $productId !== '') {
            $uniqueCodeRule = $uniqueCodeRule->ignore($productId, 'id');
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:100', $uniqueCodeRule],
            'description' => ['nullable', 'string'],
            'type' => ['nullable', 'string', 'in:product,service'],
            'price' => Rule::when(
                $this->input('type') !== 'service',
                ['required', 'numeric', 'min:0'],
                ['nullable', 'numeric', 'min:0']
            ),
            'cost' => ['nullable', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:50'],
            'stock_quantity' => ['nullable', 'integer', 'min:0'],
            'min_stock' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
            'is_featured' => ['nullable', 'boolean'],
            'track_stock' => ['nullable', 'boolean'],
            'stock' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * Retorna mensagens de erro personalizadas para as regras de validação.
     *
     * @return array<string, string> Mensagens customizadas.
     */
    public function messages(): array
    {
        return [
            'code.unique' => 'Este código já está sendo usado por outro produto da sua empresa.',
        ];
    }
}
