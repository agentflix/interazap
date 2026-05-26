<?php

declare(strict_types=1);

namespace Domain\Reports\Contracts;

use Domain\Reports\DTOs\ReportsFilterDTO;

/**
 * Contrato para todas as actions de relatório resolvidas pelo ReportActionRegistry.
 */
interface ReportActionInterface
{
    /**
     * Executa o relatório e retorna os dados estruturados.
     *
     * @param  ReportsFilterDTO  $dto  Filtros do relatório (tenant, datas, dimensões)
     * @return array<string, mixed> Dados do relatório organizados por seção
     */
    public function execute(ReportsFilterDTO $dto): array;
}
