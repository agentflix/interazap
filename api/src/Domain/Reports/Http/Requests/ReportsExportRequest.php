<?php

declare(strict_types=1);

namespace Domain\Reports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação de request para exportação de relatórios.
 */
final class ReportsExportRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (string) $user->tenant_id !== '';
    }

    /**
     * Regras de validação para exportação (inclui filtros + formato).
     *
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return array_merge(
            (new ReportsFilterRequest)->rules(),
            [
                'format' => ['required', 'string', 'in:csv,xlsx'],
            ]
        );
    }
}
