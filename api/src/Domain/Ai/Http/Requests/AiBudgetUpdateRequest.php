<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para atualização do orçamento de tokens do tenant.
 */
final class AiBudgetUpdateRequest extends FormRequest
{
    /** Verifica se o usuário possui permissão para gerenciar autopilots. */
    public function authorize(): bool
    {
        return $this->user()->can('ai.autopilots.manage');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'daily_limit' => ['sometimes', 'nullable', 'numeric', 'min:0', 'required_without:monthly_limit'],
            'monthly_limit' => ['sometimes', 'nullable', 'numeric', 'min:0', 'required_without:daily_limit'],
        ];
    }
}
