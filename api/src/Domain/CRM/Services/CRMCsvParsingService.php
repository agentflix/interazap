<?php

declare(strict_types=1);

namespace Domain\CRM\Services;

use League\Csv\Reader;
use League\Csv\Statement;

/**
 * Utilitários para análise e pré-visualização de arquivos CSV durante a importação de contatos.
 */
final class CRMCsvParsingService
{
    /**
     * Detecta automaticamente o melhor delimitador para um arquivo CSV.
     *
     * @param  string  $filePath  Caminho absoluto do arquivo CSV
     * @return string O delimitador mais adequado (,  ;  \t  |)
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
     * Conta as linhas não-vazias de um arquivo CSV.
     *
     * @param  string  $filePath  Caminho absoluto do arquivo
     * @param  string  $delimiter  Delimitador de colunas
     * @param  bool  $hasHeader  Indica se a primeira linha é cabeçalho (não contada)
     * @return int Total de linhas de dados
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
     * Extrai cabeçalhos e linhas de amostra de um arquivo CSV para pré-visualização.
     *
     * @param  string  $filePath  Caminho absoluto do arquivo
     * @param  string  $delimiter  Delimitador de colunas
     * @param  int  $sampleRows  Quantidade máxima de linhas de amostra
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
