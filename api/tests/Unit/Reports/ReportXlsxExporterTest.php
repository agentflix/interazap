<?php

declare(strict_types=1);

use Domain\Reports\Exports\ReportXlsxExporter;

describe('ReportXlsxExporter', function (): void {
    it('prepares rows and headings from flattened data', function (): void {
        $exporter = new ReportXlsxExporter;

        $exporter->prepare([
            'summary' => [
                'total' => 10,
                'amount' => 250.0,
                'nested' => ['ignore' => true],
            ],
            'items' => [
                ['label' => 'A', 'count' => 2],
                ['label' => 'B', 'count' => 8, 'amount' => 200],
            ],
        ], 'very-long-report-name-that-should-be-truncated-to-31-chars');

        expect($exporter->headings())->toBe(['section', 'total', 'amount', 'label', 'count'])
            ->and($exporter->array())->toBe([
                ['summary', 10, 250.0, '', ''],
                ['items', '', '', 'A', 2],
                ['items', '', 200, 'B', 8],
            ])
            ->and($exporter->title())->toBe('very-long-report-name-that-shou');
    });

    it('keeps section heading when associative data has only nested values', function (): void {
        $exporter = new ReportXlsxExporter;

        $exporter->prepare([
            'summary' => [],
            'meta' => [
                'nested' => ['ignored' => true],
            ],
        ], 'report');

        expect($exporter->headings())->toBe(['section'])
            ->and($exporter->array())->toBe([
                ['meta'],
            ])
            ->and($exporter->title())->toBe('report');
    });
});
