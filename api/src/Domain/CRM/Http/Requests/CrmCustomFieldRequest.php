<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para campos personalizados CRM.
 */
class CRMCustomFieldRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('crm.customs.manage');
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
            'type' => ['required', 'string', 'in:text,number,date,select,multiselect,boolean'],
            'entity' => ['nullable', 'string', 'in:contact,company,negotiation'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string'],
            'is_required' => ['nullable', 'boolean'],
        ];
    }
}
