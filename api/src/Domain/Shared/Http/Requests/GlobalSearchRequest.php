<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * FormRequest para validação do payload do endpoint de busca global.
 *
 * Exige autenticação via Sanctum e valida o termo de busca,
 * os tipos de entidade e o limite de resultados por tipo.
 */
final class GlobalSearchRequest extends FormRequest
{
    /**
     * Autoriza somente usuários autenticados via Sanctum.
     *
     * @return bool Verdadeiro se o usuário estiver autenticado.
     */
    public function authorize(): bool
    {
        return $this->user('sanctum') !== null;
    }

    /**
     * Define as regras de validação do payload de busca.
     *
     * @return array<string, array<int, string>> Mapa de campo para regras.
     */
    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:100'],
            'types' => ['nullable', 'array'],
            'types.*' => ['string', 'in:contacts,companies,negotiations,tickets,users'],
            'per_type' => ['nullable', 'integer', 'min:1', 'max:20'],
        ];
    }
}
