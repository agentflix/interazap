<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para aceitar/rejeitar proposta pública.
 */
class CRMProposalPublicRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['nullable', 'string', 'in:accept,reject'],
        ];
    }
}
