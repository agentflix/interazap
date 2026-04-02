<?php

declare(strict_types=1);

namespace Domain\CRM\Services;

use League\Csv\Reader;
use League\Csv\Statement;

/**
 * Helpers for parsing and previewing CSV files used during contact import.
 */
final class CRMCsvParsingService
{
    /**
     * Auto-detect the best delimiter for a CSV file.
     */
    public function detectDelimiter(string $filePath): string
    {
        $candidates = [',', ';', "\t", '|'];
        $bestDelimiter = ',';
        $maxColumns = 0;

        foreach ($candidates as $candidate) {
            try {
                $reader = Reader::createFromPath($filePath);
                $reader->setDelimiter($candidate);
                $row = $reader->fetchOne();
            } catch (\Throwable) {
                continue;
            }

            $columns = count($row);

            if ($columns > $maxColumns) {
                $maxColumns = $columns;
                $bestDelimiter = $candidate;
            }
        }

        return $bestDelimiter;
    }

    /**
     * Count non-empty data rows in a CSV file.
     */
    public function countRows(string $filePath, string $delimiter, bool $hasHeader): int
    {
        try {
            $reader = Reader::createFromPath($filePath);
            $reader->setDelimiter($delimiter);

            if ($hasHeader) {
                $reader->setHeaderOffset(0);
            }

            $count = 0;
            foreach ($reader->getRecords() as $record) {
                $nonEmptyValues = array_filter($record, fn ($value): bool => $value !== null && $value !== '');
                if ($nonEmptyValues === []) {
                    continue;
                }

                $count++;
            }

            return $count;
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * Extract headers and sample rows from a CSV file.
     *
     * @return array{header: array<int, string>, sample: array<int, array<int, string|null>>}
     */
    public function getPreview(string $filePath, string $delimiter, int $sampleRows): array
    {
        $reader = Reader::createFromPath($filePath);
        $reader->setDelimiter($delimiter);
        $reader->setHeaderOffset(0);

        $statement = Statement::create()->limit($sampleRows);
        $sample = [];

        foreach ($statement->process($reader) as $record) {
            $sample[] = array_values($record);
        }

        return [
            'header' => $reader->getHeader(),
            'sample' => $sample,
        ];
    }
}
