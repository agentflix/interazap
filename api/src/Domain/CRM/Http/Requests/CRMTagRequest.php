<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Requests;

use Domain\CRM\Models\CRMTag;
use Domain\Shared\Http\Requests\BaseCrudFormRequest;

/**
 * Validação para tag CRM.
 */
class CRMTagRequest extends BaseCrudFormRequest
{
    /**
     * @return class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected function modelClass(): string
    {
        return CRMTag::class;
    }

    /**
     * Retorna as regras de validação aplicadas à requisição.
     *
     * @return array<string, mixed> Regras de validação.
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['nullable', 'string', 'max:32'],
            'category' => ['nullable', 'string', 'max:255'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }
}
