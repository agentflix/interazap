<?php

declare(strict_types=1);

namespace Domain\Reports\Exports\Concerns;

/**
 * Métodos compartilhados para conversão de dados de relatório em formato tabular.
 */
trait FlattensReportData
{
    /**
     * Converte dados aninhados em linhas tabulares.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    private function flattenReport(array $data): array
    {
        $rows = [];

        foreach ($data as $section => $content) {
            if (is_array($content) && $content !== []) {
                if ($this->isAssociative($content)) {
                    $row = ['section' => $section];
                    foreach ($content as $key => $value) {
                        if (! is_array($value)) {
                            $row[$key] = $value;
                        }
                    }
                    $rows[] = $row;
                } else {
                    foreach ($content as $item) {
                        if (is_array($item)) {
                            $row = ['section' => $section];
                            foreach ($item as $key => $value) {
                                if (! is_array($value)) {
                                    $row[$key] = $value;
                                }
                            }
                            $rows[] = $row;
                        }
                    }
                }
            }
        }

        if ($rows === []) {
            return [];
        }

        /** @var array<string, null> $allKeys */
        $allKeys = [];
        foreach ($rows as $row) {
            foreach (array_keys($row) as $key) {
                $allKeys[$key] = null;
            }
        }

        return array_map(function (array $row) use ($allKeys): array {
            $normalized = [];
            foreach (array_keys($allKeys) as $key) {
                $normalized[$key] = $row[$key] ?? '';
            }

            return $normalized;
        }, $rows);
    }

    /**
     * Verifica se um array é associativo.
     *
     * @param  array<mixed>  $array
     */
    private function isAssociative(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
