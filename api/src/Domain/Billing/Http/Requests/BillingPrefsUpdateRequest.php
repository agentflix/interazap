<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para atualização das preferências de cobrança do tenant.
 *
 * Apenas administradores podem alterar o modo de excedente da conta.
 */
final class BillingPrefsUpdateRequest extends FormRequest
{
    /**
     * Verifica se o usuário está autorizado a atualizar preferências de cobrança.
     *
     * Restrito a qualquer tipo de administrador do tenant.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->isAnyAdmin();
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'overage_mode_override' => ['nullable', 'in:stop,overage'],
        ];
    }
}
