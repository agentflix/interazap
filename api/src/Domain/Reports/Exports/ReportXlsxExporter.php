<?php

declare(strict_types=1);

namespace Domain\Reports\Exports;

use Domain\Reports\Exports\Concerns\FlattensReportData;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;

/**
 * Exporta dados de relatório para formato XLSX usando maatwebsite/excel.
 */
final class ReportXlsxExporter implements FromArray, ShouldAutoSize, WithHeadings, WithTitle
{
    use FlattensReportData;

    /** @var array<int, array<string, mixed>> */
    private array $rows = [];

    /** @var string[] */
    private array $headings = [];

    private string $sheetTitle = 'Report';

    /**
     * Prepara o exporter com dados do relatório.
     *
     * @param  array<string, mixed>  $data
     */
    public function prepare(array $data, string $reportName): self
    {
        $this->sheetTitle = mb_substr($reportName, 0, 31);
        $this->rows = $this->flattenReport($data);

        if ($this->rows !== []) {
            $this->headings = array_keys($this->rows[0]);
        }

        return $this;
    }

    /**
     * @return array<int, list<mixed>>
     */
    public function array(): array
    {
        return array_map(fn (array $row): array => array_values($row), $this->rows);
    }

    /**
     * @return string[]
     */
    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return $this->sheetTitle;
    }
}
