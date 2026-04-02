<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Actions\ChatQuickAnswerActions;
use Domain\Chat\DTOs\ChatQuickAnswerDTO;
use Domain\Chat\Http\Requests\ChatQuickAnswerRequest;
use Domain\Chat\Http\Resources\ChatQuickAnswerResource;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de Respostas Rápidas (Quick Answers).
 *
 * Permite a gestão de atalhos textuais utilizados pelos atendentes para
 * agilizar o suporte no chat. Oferece CRUD e listagem completa para uso em editores.
 *
 * @category Controllers
 */
final class ChatQuickAnswerController extends BaseController
{
    public function __construct(
        private readonly ChatQuickAnswerActions $actions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Listar respostas rápidas detalhadas com suporte a filtros.
     *
     * @param  Request  $request  Solicitação HTTP com filtros (search, category, is_active).
     * @return JsonResponse Lista paginada de respostas.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \Domain\Chat\Models\ChatQuickAnswer::class);

        $tenantId = (string) $request->user()->tenant_id;
        $paginator = $this->actions->list($tenantId, $request->only(['search', 'category', 'is_active']));
        $paginator->getCollection()->transform(fn ($item) => new ChatQuickAnswerResource($item));

        return $this->paginated($paginator);
    }

    /**
     * Registrar uma nova resposta rápida.
     *
     * @param  ChatQuickAnswerRequest  $request  Dados validados (name, shortcut, content).
     * @return JsonResponse Recurso da resposta criada.
     */
    public function store(ChatQuickAnswerRequest $request): JsonResponse
    {
        $this->authorize('create', \Domain\Chat\Models\ChatQuickAnswer::class);

        $tenantId = (string) $request->user()->tenant_id;
        $qa = $this->actions->create($tenantId, ChatQuickAnswerDTO::fromRequest($request));
        $this->audit->log($request->user(), $tenantId, 'chat.quick_answers.created', $qa);

        return $this->created(new ChatQuickAnswerResource($qa), 'Resposta rápida criada');
    }

    /**
     * Exibir detalhes de uma resposta rápida específica.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID da resposta.
     * @return JsonResponse Objeto da resposta rápida.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $qa = $this->actions->find($tenantId, $id);
        $this->authorize('view', $qa);

        return $this->success(new ChatQuickAnswerResource($qa), 'Resposta carregada');
    }

    /**
     * Atualizar os dados de uma resposta rápida.
     *
     * @param  ChatQuickAnswerRequest  $request  Dados validados.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Registro atualizado.
     */
    public function update(ChatQuickAnswerRequest $request, string $id): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $qa = $this->actions->find($tenantId, $id);
        $this->authorize('update', $qa);

        $qa = $this->actions->update($tenantId, $id, ChatQuickAnswerDTO::fromRequest($request));
        $this->audit->log($request->user(), $tenantId, 'chat.quick_answers.updated', $qa, $qa->getChanges());

        return $this->success(new ChatQuickAnswerResource($qa), 'Resposta atualizada');
    }

    /**
     * Remover uma resposta rápida do sistema.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Confirmação de exclusão.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $qa = $this->actions->find($tenantId, $id);
        $this->authorize('delete', $qa);

        $this->actions->delete($tenantId, $id);
        $this->audit->log($request->user(), $tenantId, 'chat.quick_answers.deleted', $qa);

        return $this->deleted();
    }

    /**
     * Obter lista simplificada de todas as respostas ativas do tenant.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @return JsonResponse Coleção de respostas rápidas ativas.
     */
    public function all(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \Domain\Chat\Models\ChatQuickAnswer::class);

        $tenantId = (string) $request->user()->tenant_id;
        $items = $this->actions->listAllActive($tenantId)->map(fn ($qa) => new ChatQuickAnswerResource($qa));

        return $this->success($items, 'Respostas rápidas ativas');
    }
}
