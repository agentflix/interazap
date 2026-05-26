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
 * Controller para arquivos anexados a negociações do CRM.
 *
 * Gerencia upload, listagem e remoção de arquivos em negociações. Requer autenticação Sanctum.
 */
final class CRMNegotiationFileController extends BaseController
{
    /**
     * @param  CRMNegotiationFileActions  $actions  Ação de gestão de arquivos de negociação.
     */
    public function __construct(
        private readonly CRMNegotiationFileActions $actions
    ) {}

    /**
     * Lista arquivos de uma negociação.
     *
     * @param  Request  $request  Dados da requisição.
     * @param  string  $negotiationId  ID da negociação.
     * @return JsonResponse Lista de arquivos.
     */
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

    /**
     * Envia arquivo para uma negociação.
     *
     * @param  CRMNegotiationFileRequest  $request  Dados da requisição com o arquivo.
     * @param  string  $negotiationId  ID da negociação.
     * @return JsonResponse Arquivo enviado.
     */
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

    /**
     * Remove arquivo de uma negociação.
     *
     * @param  Request  $request  Dados da requisição.
     * @param  string  $negotiationId  ID da negociação.
     * @param  string  $fileId  ID do arquivo.
     * @return JsonResponse Resposta sem conteúdo.
     */
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
