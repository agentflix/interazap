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
     * Determina se o usuário está autorizado a fazer esta requisição. Acesso público sem autenticação.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retorna as regras de validação aplicadas à requisição.
     *
     * @return array<string, mixed> Regras de validação.
     */
    public function rules(): array
    {
        return [
            'action' => ['nullable', 'string', 'in:accept,reject'],
        ];
    }
}
