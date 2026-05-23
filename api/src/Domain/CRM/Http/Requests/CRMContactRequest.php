<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Domain\Shared\Rules\TenantExistsRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para criação/atualização de contato CRM.
 */
class CRMContactRequest extends FormRequest
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
            'email' => ['nullable', 'email', 'max:255'],
            'document' => ['nullable', 'string', 'max:32'],
            'phone' => ['nullable', 'string', 'max:32'],
            'phone_e164' => ['nullable', 'string', 'max:32'],
            'position' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:500'],
            'custom_fields' => ['nullable', 'array'],
            'custom_fields.*' => ['nullable', 'string', 'max:500'],
            'crm_company_id' => ['nullable', 'uuid', new TenantExistsRule('crm_companies')],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
