<?php

declare(strict_types=1);

namespace Domain\Reports\Actions;

use Domain\Reports\Contracts\ReportActionInterface;
use Domain\Reports\ReportConstants;
use Illuminate\Support\Carbon;

/**
 * Classe base abstrata para todas as actions de relatório.
 *
 * Fornece utilitários compartilhados: construção de chave de cache,
 * parse de intervalo de datas, geração de CSV e TTL padrão.
 */
abstract class AbstractReportAction implements ReportActionInterface
{
    /**
     * Constrói uma chave de cache única para o relatório do tenant.
     *
     * @param  string  $tenantId  UUID do tenant
     * @param  string  $prefix  Prefixo identificador do relatório
     * @param  array<int|string, mixed>  $parts  Partes adicionais que compõem a chave
     */
    protected function buildCacheKey(string $tenantId, string $prefix, array $parts): string
    {
        return sprintf('reports:%s:%s:%s', $tenantId, $prefix, md5(serialize($parts)));
    }

    /**
     * Converte strings de data em objetos Carbon com início/fim de dia.
     *
     * @param  string|null  $startDate  Data de início no formato Y-m-d; usa hoje se nulo
     * @param  string|null  $endDate  Data de fim no formato Y-m-d; usa hoje se nulo
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function parseDateRange(?string $startDate, ?string $endDate): array
    {
        $start = $startDate !== null ? Carbon::parse($startDate)->startOfDay() : now()->startOfDay();
        $end = $endDate !== null ? Carbon::parse($endDate)->endOfDay() : now()->endOfDay();

        return [$start, $end];
    }

    /**
     * Gera conteúdo CSV a partir de linhas e colunas informadas.
     *
     * @param  array<int, array<int|string, mixed>>  $rows  Linhas de dados
     * @param  array<int, string>  $columns  Nomes das colunas (cabeçalho)
     * @return string Conteúdo CSV como string
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

    /** Retorna o TTL de cache padrão para relatórios em segundos. */
    protected function cacheTtl(): int
    {
        return ReportConstants::CACHE_TTL;
    }
}
