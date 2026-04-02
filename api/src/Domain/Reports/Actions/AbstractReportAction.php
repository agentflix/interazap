<?php

declare(strict_types=1);

namespace Domain\Reports\Actions;

use Domain\Reports\Contracts\ReportActionInterface;
use Domain\Reports\ReportConstants;
use Illuminate\Support\Carbon;

/**
 * Shared helpers for report actions.
 */
abstract class AbstractReportAction implements ReportActionInterface
{
    /**
     * @param  array<int|string, mixed>  $parts
     */
    protected function buildCacheKey(string $tenantId, string $prefix, array $parts): string
    {
        return sprintf('reports:%s:%s:%s', $tenantId, $prefix, md5(serialize($parts)));
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function parseDateRange(?string $startDate, ?string $endDate): array
    {
        $start = $startDate !== null ? Carbon::parse($startDate)->startOfDay() : now()->startOfDay();
        $end = $endDate !== null ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        return [$start, $end];
    }

    /**
     * @param  array<int, array<int|string, mixed>>  $rows
     * @param  array<int, string>  $columns
     */
    protected function toCsv(array $rows, array $columns): string
    {
        $handle = fopen('php://temp', 'rb+');

        if ($handle === false) {
            return '';
        }

        fputcsv($handle, $columns);

        foreach ($rows as $row) {
            $line = [];
            foreach ($columns as $column) {
                $line[] = $row[$column] ?? null;
            }

            fputcsv($handle, $line);
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);

        return $content === false ? '' : $content;
    }

    protected function cacheTtl(): int
    {
        return ReportConstants::CACHE_TTL;
    }
}
