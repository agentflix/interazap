<?php

declare(strict_types=1);

namespace Domain\CRM\Http\Controllers;

use Domain\CRM\Actions\CRMNegotiationFileActions;
use Domain\CRM\Http\Requests\CRMNegotiationFileRequest;
use Domain\CRM\Models\CRMNegotiation;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para arquivos de negociações.
 */
final class CRMNegotiationFileController extends BaseController
{
    public function __construct(
        private readonly CRMNegotiationFileActions $actions
    ) {}

    public function index(Request $request, string $negotiationId): JsonResponse
    {
        $tenantId = $this->tenantId();
        $negotiation = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($negotiationId);
        $this->authorize('view', $negotiation);

        $files = $this->actions->list($tenantId, $negotiationId);

        return $this->success($files, 'Arquivos listados');
    }

    public function store(CRMNegotiationFileRequest $request, string $negotiationId): JsonResponse
    {
        $tenantId = $this->tenantId();
        $negotiation = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($negotiationId);
        $this->authorize('update', $negotiation);

        $userId = (string) $request->user()?->id;
        $uploaded = $request->file('file');

        $file = $this->actions->create($tenantId, $userId, $negotiationId, $uploaded);

        return $this->created($file, 'Arquivo enviado');
    }

    public function destroy(Request $request, string $negotiationId, string $fileId): JsonResponse
    {
        $tenantId = $this->tenantId();
        $negotiation = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($negotiationId);
        $this->authorize('update', $negotiation);

        $this->actions->delete($tenantId, $negotiationId, $fileId);

        return $this->noContent();
    }
}
