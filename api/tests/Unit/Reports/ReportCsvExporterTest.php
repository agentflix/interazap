<?php

declare(strict_types=1);

use Domain\Reports\Exports\ReportCsvExporter;

describe('ReportCsvExporter', function (): void {
    it('exports section-only rows when associative data has nested values only', function (): void {
        $exporter = new ReportCsvExporter;

        $content = $exporter->export([
            'metadata' => [],
            'totals' => [
                'nested' => ['ignored' => true],
            ],
        ], 'sales-funnel');

        $lines = array_values(array_filter(explode("\n", trim($content))));

        expect($lines)->toHaveCount(2)
            ->and(str_getcsv($lines[0]))->toBe(['section'])
            ->and(str_getcsv($lines[1]))->toBe(['totals']);
    });

    it('exports flattened report with normalized columns', function (): void {
        $exporter = new ReportCsvExporter;

        $content = $exporter->export([
            'summary' => [
                'total' => 5,
                'amount' => 150.5,
                'nested' => ['ignore' => true],
            ],
            'steps' => [
                ['name' => 'Lead', 'count' => 3],
                ['name' => 'Won', 'count' => 2, 'amount' => 100],
            ],
        ], 'sales-funnel');

        $lines = array_values(array_filter(explode("\n", trim($content))));

        expect($lines)->toHaveCount(4);

        $header = str_getcsv($lines[0]);
        $row1 = str_getcsv($lines[1]);
        $row2 = str_getcsv($lines[2]);
        $row3 = str_getcsv($lines[3]);

        expect($header)->toBe(['section', 'total', 'amount', 'name', 'count'])
            ->and($row1)->toBe(['summary', '5', '150.5', '', ''])
            ->and($row2)->toBe(['steps', '', '', 'Lead', '3'])
            ->and($row3)->toBe(['steps', '', '100', 'Won', '2']);
    });
});
