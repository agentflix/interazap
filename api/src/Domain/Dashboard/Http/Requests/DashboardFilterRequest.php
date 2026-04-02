<?php

declare(strict_types=1);

namespace Domain\Dashboard\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

/**
 * Validação dos filtros do dashboard.
 */
final class DashboardFilterRequest extends FormRequest
{
    /**
     * Autorização delegada ao controller.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'period' => ['sometimes', 'integer', 'min:1', 'max:365'],
            'date_from' => ['sometimes', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }

    /**
     * Obter o intervalo de datas do período solicitado.
     *
     * @return array{from: Carbon, to: Carbon}
     */
    public function period(): array
    {
        if ($this->has(['date_from', 'date_to'])) {
            return [
                'from' => Carbon::parse($this->input('date_from'))->startOfDay(),
                'to' => Carbon::parse($this->input('date_to'))->endOfDay(),
            ];
        }

        $days = (int) $this->input('period', 30);

        return [
            'from' => Carbon::now()->subDays($days - 1)->startOfDay(),
            'to' => Carbon::now()->endOfDay(),
        ];
    }
}
