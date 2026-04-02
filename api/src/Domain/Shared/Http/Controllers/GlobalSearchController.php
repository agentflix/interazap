<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Controllers;

use Domain\Shared\Actions\GlobalSearchAction;
use Domain\Shared\DTOs\GlobalSearchDTO;
use Domain\Shared\Http\Requests\GlobalSearchRequest;
use Domain\Shared\Http\Resources\GlobalSearchResultResource;

/**
 * Global search endpoint for spotlight queries.
 *
 * Provides unified search across contacts, companies,
 * negotiations, tickets, and users.
 *
 * @category Controllers
 */
final class GlobalSearchController extends BaseController
{
    /**
     * Execute global search with filters.
     *
     * @param  GlobalSearchRequest  $request  Validated search request with query and types.
     * @param  GlobalSearchAction  $action  Search action handler.
     * @return GlobalSearchResultResource Search results grouped by type.
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
