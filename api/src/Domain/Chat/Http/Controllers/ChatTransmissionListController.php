<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Actions\ChatTransmissionListActions;
use Domain\Chat\DTOs\ChatTransmissionListDTO;
use Domain\Chat\Http\Requests\ChatTransmissionListAudienceRequest;
use Domain\Chat\Http\Requests\ChatTransmissionListPreviewRequest;
use Domain\Chat\Http\Requests\ChatTransmissionListRequest;
use Domain\Chat\Http\Resources\ChatTransmissionListResource;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de Listas de Transmissão de Mensagens.
 *
 * @category Controllers
 */
final class ChatTransmissionListController extends BaseController
{
    public function __construct(
        private readonly ChatTransmissionListActions $actions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Listar as listas de transmissão do tenant.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @return JsonResponse Lista paginada de listas.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \Domain\Chat\Models\ChatTransmissionList::class);

        $tenantId = (string) $request->user()->tenant_id;
        $paginator = $this->actions->list($tenantId);
        $paginator->getCollection()->transform(fn ($item) => new ChatTransmissionListResource($item));

        return $this->paginated($paginator);
    }

    /**
     * Criar uma nova lista de transmissão.
     *
     * @param  ChatTransmissionListRequest  $request  Dados validados.
     * @return JsonResponse Registro da lista criada.
     */
    public function store(ChatTransmissionListRequest $request): JsonResponse
    {
        $this->authorize('create', \Domain\Chat\Models\ChatTransmissionList::class);

        $tenantId = (string) $request->user()->tenant_id;
        $transmissionList = $this->actions->create($tenantId, ChatTransmissionListDTO::fromRequest($request));
        $this->audit->log($request->user(), $tenantId, 'chat.transmission_lists.created', $transmissionList);

        return $this->created(new ChatTransmissionListResource($transmissionList), 'Lista de transmissão criada');
    }

    /**
     * Exibir detalhes de uma lista de transmissão.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Objeto da lista.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $transmissionList = $this->actions->find($tenantId, $id);
        $this->authorize('view', $transmissionList);

        return $this->success(new ChatTransmissionListResource($transmissionList), 'Lista de transmissão carregada');
    }

    /**
     * Atualizar dados de uma lista de transmissão existente.
     *
     * @param  ChatTransmissionListRequest  $request  Dados validados.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Registro atualizado.
     */
    public function update(ChatTransmissionListRequest $request, string $id): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $transmissionList = $this->actions->find($tenantId, $id);
        $this->authorize('update', $transmissionList);

        $transmissionList = $this->actions->update($tenantId, $id, ChatTransmissionListDTO::fromRequest($request));
        $this->audit->log($request->user(), $tenantId, 'chat.transmission_lists.updated', $transmissionList, $transmissionList->getChanges());

        return $this->success(new ChatTransmissionListResource($transmissionList), 'Lista de transmissão atualizada');
    }

    /**
     * Remover uma lista de transmissão.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Confirmação de exclusão.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $transmissionList = $this->actions->find($tenantId, $id);
        $this->authorize('delete', $transmissionList);

        $this->actions->delete($tenantId, $id);
        $this->audit->log($request->user(), $tenantId, 'chat.transmission_lists.deleted', $transmissionList);

        return $this->deleted();
    }

    /**
     * Iniciar manualmente o disparo da lista de transmissão.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Lista com status atualizado para 'running' ou similar.
     */
    public function send(Request $request, string $id): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $transmissionList = $this->actions->find($tenantId, $id);
        $this->authorize('update', $transmissionList);

        $transmissionList = $this->actions->send($tenantId, $id);
        $this->audit->log($request->user(), $tenantId, 'chat.transmission_lists.sent', $transmissionList);

        return $this->success(new ChatTransmissionListResource($transmissionList), 'Lista de transmissão enviada com sucesso');
    }

    /**
     * Gerar preview da mensagem.
     */
    public function preview(ChatTransmissionListPreviewRequest $request): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $message = $request->validated('message');

        $data = $this->actions->preview($tenantId, $message);

        return $this->success($data);
    }

    /**
     * Contar público alvo baseado em filtros.
     */
    public function audience(ChatTransmissionListAudienceRequest $request): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $criteria = $request->validated('criteria', []);

        $count = $this->actions->countAudience($tenantId, $criteria);

        return $this->success(['count' => $count]);
    }
}
