<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Domain\Shared\Rules\TenantExistsRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para atualização parcial de contato CRM.
 */
class CRMContactPatchRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'min:3', 'max:255'],
            'email' => ['sometimes', 'nullable', 'email', 'max:255'],
            'document' => ['sometimes', 'nullable', 'string', 'max:32'],
            'crm_company_id' => ['sometimes', 'nullable', 'uuid', new TenantExistsRule('crm_companies')],
            'ticket_id' => ['sometimes', 'nullable', 'uuid'],
        ];
    }
}
