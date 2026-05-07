<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Controllers;

use Domain\Platform\Actions\PlatformLeadAdminActions;
use Domain\Platform\Http\Resources\PlatformLeadAdminResource;
use Domain\Platform\Models\PlatformLead;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador administrativo para leads da plataforma.
 */
final class PlatformLeadAdminController extends BaseController
{
    public function __construct(
        private readonly PlatformLeadAdminActions $actions,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PlatformLead::class);

        $filters = $request->only([
            'search',
            'status',
            'source',
            'page',
            'per_page',
            'sort_by',
            'sort_dir',
        ]);

        $paginator = $this->actions->list($filters);
        $paginator->getCollection()->transform(fn ($item) => new PlatformLeadAdminResource($item));

        return $this->paginated($paginator, 'Leads listados');
    }
}
