<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação de submissão pública da avaliação de ticket.
 */
final class ChatTicketEvaluationPublicRequest extends FormRequest
{
    /**
     * Determina se a requisição pública está autorizada.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Regras de validação da submissão pública.
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
