<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource para serialização do resumo de consumo de IA.
 */
final class UsageSummaryResource extends JsonResource
{
    /**
     * Transforma o recurso em array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'period_start' => $this->resource['period_start'],
            'period_end' => $this->resource['period_end'],
            'total_requests' => $this->resource['total_requests'],
            'total_input_tokens' => $this->resource['total_input_tokens'],
            'total_output_tokens' => $this->resource['total_output_tokens'],
            'total_tokens' => $this->resource['total_tokens'],
            'total_cost' => $this->resource['total_cost'],
            'formatted_cost' => '$'.number_format($this->resource['total_cost'], 2),
            'avg_latency_ms' => $this->resource['avg_latency_ms'],
            'cost_change_percent' => $this->resource['cost_change_percent'],
            'cost_trend' => $this->getCostTrend(),
        ];
    }

    private function getCostTrend(): string
    {
        $change = $this->resource['cost_change_percent'] ?? 0;

        if ($change > 5) {
            return 'up';
        } elseif ($change < -5) {
            return 'down';
        }

        return 'stable';
    }
}
