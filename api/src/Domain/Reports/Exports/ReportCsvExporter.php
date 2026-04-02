<?php

declare(strict_types=1);

namespace Domain\Reports\Exports;

use Domain\Reports\Exports\Concerns\FlattensReportData;
use League\Csv\Writer;
use SplTempFileObject;

/**
 * Exporta dados de relatório para formato CSV.
 */
final class ReportCsvExporter
{
    use FlattensReportData;

    /**
     * Gera o conteúdo CSV a partir de um array de relatório.
     *
     * @param  array<string, mixed>  $data
     * @return string O conteúdo CSV como string
     */
    public function export(array $data, string $reportName): string
    {
        $rows = $this->flattenReport($data);

        if ($rows === []) {
            return '';
        }

        $csv = Writer::createFromFileObject(new SplTempFileObject);
        $csv->setDelimiter(',');
        $csv->setEnclosure('"');

        $csv->insertOne(array_keys($rows[0]));
        $csv->insertAll($rows);

        return $csv->toString();
    }
}
