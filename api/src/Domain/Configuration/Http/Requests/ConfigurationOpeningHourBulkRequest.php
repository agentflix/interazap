<?php

declare(strict_types=1);

namespace Domain\Configuration\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validação para atualização em lote dos horários.
 */
final class ConfigurationOpeningHourBulkRequest extends FormRequest
{
    /** Verifica se o usuário está autorizado a realizar esta requisição. */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && (string) $user->tenant_id !== '';
    }

    /**
     * Retorna as regras de validação aplicadas à requisição.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'opening_hours' => ['required', 'array'],
            'opening_hours.*.day_of_week' => ['required', 'integer', 'between:0,6'],
            'opening_hours.*.open_time' => ['required', 'date_format:H:i'],
            'opening_hours.*.close_time' => ['required', 'date_format:H:i'],
            'opening_hours.*.is_active' => ['boolean'],
        ];
    }
}
