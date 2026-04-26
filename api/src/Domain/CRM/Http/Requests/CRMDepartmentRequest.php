<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Domain\CRM\Models\CRMDepartment;
use Domain\Shared\Http\Requests\BaseCrudFormRequest;

/**
 * Validação para departamento.
 */
class CRMDepartmentRequest extends BaseCrudFormRequest
{
    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected function modelClass(): string
    {
        return CRMDepartment::class;
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
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
