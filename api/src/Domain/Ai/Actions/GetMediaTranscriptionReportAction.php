<?php

declare(strict_types=1);

namespace Domain\Ai\Actions;

use Domain\Ai\Models\AiUsageLog;
use Illuminate\Support\Carbon;

/**
 * Action para gerar relatório de uso de transcrição de mídia.
 *
 * Retorna métricas consolidadas de transcrição (áudio, imagem, vídeo)
 * incluindo contagens, custos, tokens e breakdown por tipo/dia.
 *
 * @category Actions
 */
final class GetMediaTranscriptionReportAction
{
    /**
     * Executar geração do relatório.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $startDate  Data início (Y-m-d).
     * @param  string  $endDate  Data fim (Y-m-d).
     * @return array<string, mixed>
     */
    public function execute(string $tenantId, string $startDate, string $endDate): array
    {
        $start = Carbon::parse($startDate)->startOfDay();
        $end = Carbon::parse($endDate)->endOfDay();

        $baseQuery = AiUsageLog::query()
            ->where('tenant_id', $tenantId)
            ->where('feature', 'like', 'media_transcription_%')
            ->whereBetween('created_at', [$start, $end]);

        // Summary
        $summary = (clone $baseQuery)
            ->selectRaw('
                COUNT(*) as total_transcriptions,
                COALESCE(SUM(input_tokens + output_tokens), 0) as total_tokens,
                COALESCE(SUM(input_cost + output_cost), 0) as total_cost,
                COALESCE(AVG(latency_ms), 0) as avg_latency_ms
            ')
            ->first();

        // Breakdown by media type (stored in metadata->media_type)
        $byType = (clone $baseQuery)
            ->selectRaw("
                metadata->>'media_type' as media_type,
                COUNT(*) as count,
                COALESCE(SUM(input_tokens + output_tokens), 0) as tokens,
                COALESCE(SUM(input_cost + output_cost), 0) as cost
            ")
            ->groupByRaw("metadata->>'media_type'")
            ->orderByDesc('cost')
            ->get()
            ->map(fn ($row) => [
                'media_type' => $row->media_type ?? 'unknown',
                'count' => (int) $row->count,
                'tokens' => (int) $row->tokens,
                'cost' => round((float) $row->cost, 6),
            ])
            ->all();

        // Daily cost
        $dailyCost = (clone $baseQuery)
            ->selectRaw('
                DATE(created_at) as date,
                COUNT(*) as count,
                COALESCE(SUM(input_cost + output_cost), 0) as cost
            ')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get()
            ->map(fn ($row) => [
                'date' => $row->date,
                'count' => (int) $row->count,
                'cost' => round((float) $row->cost, 6),
            ])
            ->all();

        // By model
        $byModel = (clone $baseQuery)
            ->selectRaw('
                model_name,
                COUNT(*) as count,
                COALESCE(SUM(input_tokens + output_tokens), 0) as tokens,
                COALESCE(SUM(input_cost + output_cost), 0) as cost
            ')
            ->groupBy('model_name')
            ->orderByDesc('cost')
            ->get()
            ->map(fn ($row) => [
                'model_name' => $row->model_name,
                'count' => (int) $row->count,
                'tokens' => (int) $row->tokens,
                'cost' => round((float) $row->cost, 6),
            ])
            ->all();

        return [
            'summary' => [
                'total_transcriptions' => (int) $summary->total_transcriptions,
                'total_tokens' => (int) $summary->total_tokens,
                'total_cost' => round((float) $summary->total_cost, 6),
                'avg_latency_ms' => round((float) $summary->avg_latency_ms, 2),
            ],
            'by_type' => $byType,
            'by_model' => $byModel,
            'daily_cost' => $dailyCost,
        ];
    }
}
