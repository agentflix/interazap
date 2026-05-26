<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Controllers;

use Domain\Shared\Actions\GlobalSearchAction;
use Domain\Shared\DTOs\GlobalSearchDTO;
use Domain\Shared\Http\Requests\GlobalSearchRequest;
use Domain\Shared\Http\Resources\GlobalSearchResultResource;

/**
 * Endpoint de busca global estilo spotlight.
 *
 * Fornece busca unificada em contatos, empresas, negociações, tickets e usuários,
 * agrupando os resultados por tipo com contagem total e duração da operação.
 */
final class GlobalSearchController extends BaseController
{
    /**
     * Executa a busca global e retorna resultados agrupados por tipo.
     *
     * @param  GlobalSearchRequest  $request  Requisição validada com termo e tipos de busca.
     * @param  GlobalSearchAction  $action  Action responsável pela busca em múltiplos domínios.
     * @return GlobalSearchResultResource Resultados agrupados por tipo de entidade.
     */
    public function __invoke(GlobalSearchRequest $request, GlobalSearchAction $action): GlobalSearchResultResource
    {
        $this->authorize('search');

        $startedAt = hrtime(true);
        $dto = GlobalSearchDTO::fromRequest($request);
        $results = $action->handle($dto);
        $durationMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        return new GlobalSearchResultResource([
            'query' => $dto->query,
            'per_type' => $dto->perType,
            'duration_ms' => $durationMs,
            'data' => $results,
        ]);
    }
}
