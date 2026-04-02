<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Actions\ChatCampaignActions;
use Domain\Chat\DTOs\ChatCampaignDTO;
use Domain\Chat\Http\Requests\ChatCampaignAudienceRequest;
use Domain\Chat\Http\Requests\ChatCampaignPreviewRequest;
use Domain\Chat\Http\Requests\ChatCampaignRequest;
use Domain\Chat\Http\Resources\ChatCampaignResource;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de Campanhas de Mensagens.
 *
 * Gerencia o ciclo de vida de disparos em lote (campanhas), permitindo
 * criação, agendamento, monitoramento de progresso e execução.
 *
 * @category Controllers
 */
final class ChatCampaignController extends BaseController
{
    public function __construct(
        private readonly ChatCampaignActions $actions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Listar as campanhas do tenant.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @return JsonResponse Lista paginada de campanhas.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \Domain\Chat\Models\ChatCampaign::class);

        $tenantId = (string) $request->user()->tenant_id;
        $paginator = $this->actions->list($tenantId);
        $paginator->getCollection()->transform(fn ($item) => new ChatCampaignResource($item));

        return $this->paginated($paginator);
    }

    /**
     * Criar uma nova campanha.
     *
     * @param  ChatCampaignRequest  $request  Dados validados.
     * @return JsonResponse Registro da campanha criada.
     */
    public function store(ChatCampaignRequest $request): JsonResponse
    {
        $this->authorize('create', \Domain\Chat\Models\ChatCampaign::class);

        $tenantId = (string) $request->user()->tenant_id;
        $campaign = $this->actions->create($tenantId, ChatCampaignDTO::fromRequest($request));
        $this->audit->log($request->user(), $tenantId, 'chat.campaigns.created', $campaign);

        return $this->created(new ChatCampaignResource($campaign), 'Campanha criada');
    }

    /**
     * Exibir detalhes de uma campanha.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Objeto da campanha.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $campaign = $this->actions->find($tenantId, $id);
        $this->authorize('view', $campaign);

        return $this->success(new ChatCampaignResource($campaign), 'Campanha carregada');
    }

    /**
     * Atualizar dados de uma campanha existente.
     *
     * @param  ChatCampaignRequest  $request  Dados validados.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Registro atualizado.
     */
    public function update(ChatCampaignRequest $request, string $id): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $campaign = $this->actions->find($tenantId, $id);
        $this->authorize('update', $campaign);

        $campaign = $this->actions->update($tenantId, $id, ChatCampaignDTO::fromRequest($request));
        $this->audit->log($request->user(), $tenantId, 'chat.campaigns.updated', $campaign, $campaign->getChanges());

        return $this->success(new ChatCampaignResource($campaign), 'Campanha atualizada');
    }

    /**
     * Remover uma campanha.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Confirmação de exclusão.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $campaign = $this->actions->find($tenantId, $id);
        $this->authorize('delete', $campaign);

        $this->actions->delete($tenantId, $id);
        $this->audit->log($request->user(), $tenantId, 'chat.campaigns.deleted', $campaign);

        return $this->deleted();
    }

    /**
     * Iniciar manualmente o disparo da campanha.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Campanha com status atualizado para 'running' ou similar.
     */
    public function send(Request $request, string $id): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $campaign = $this->actions->find($tenantId, $id);
        $this->authorize('update', $campaign);

        $campaign = $this->actions->send($tenantId, $id);
        $this->audit->log($request->user(), $tenantId, 'chat.campaigns.sent', $campaign);

        return $this->success(new ChatCampaignResource($campaign), 'Campanha enviada com sucesso');
    }

    /**
     * Gerar preview da mensagem.
     */
    public function preview(ChatCampaignPreviewRequest $request): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $message = $request->validated('message');

        $data = $this->actions->preview($tenantId, $message);

        return $this->success($data);
    }

    /**
     * Contar público alvo baseado em filtros.
     */
    public function audience(ChatCampaignAudienceRequest $request): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $criteria = $request->validated('criteria', []);

        $count = $this->actions->countAudience($tenantId, $criteria);

        return $this->success(['count' => $count]);
    }
}
