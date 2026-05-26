<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest base para filtros de listagem compartilhados entre domínios.
 *
 * Define as regras comuns de paginação, busca, ordenação e direção
 * que podem ser estendidas por requests específicos de cada domínio.
 */
class FilterRequest extends FormRequest
{
    /**
     * Autoriza a requisição — sempre permitida para usuários autenticados.
     *
     * @return bool Sempre verdadeiro.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Retorna as regras de validação para os parâmetros de filtro.
     *
     * @return array<string, mixed> Mapa de campo para regras de validação.
     */
    public function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'sort' => ['sometimes', 'nullable', 'string', 'max:100'],
            'direction' => ['sometimes', 'nullable', 'in:asc,desc'],
        ];
    }
}
