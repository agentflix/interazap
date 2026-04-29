<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Actions\ChatMessageTemplateActions;
use Domain\Chat\Actions\CreateMetaTemplateAction;
use Domain\Chat\Actions\DeleteChatMessageTemplateAction;
use Domain\Chat\Actions\SyncMetaTemplatesAction;
use Domain\Chat\Actions\UpdateChatMessageTemplateAction;
use Domain\Chat\Http\Requests\ChatMessageTemplateRequest;
use Domain\Chat\Http\Resources\ChatMessageTemplateResource;
use Domain\Chat\Models\ChatMessageTemplate;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de Templates de Mensagens.
 *
 * Gerencia a criação e listagem de moldes de mensagens (HSM ou texto livre)
 * que podem ser selecionados pelos atendentes para agilizar o suporte.
 *
 * @category Controllers
 */
final class ChatMessageTemplateController extends BaseController
{
    public function __construct(
        private readonly ChatMessageTemplateActions $actions,
        private readonly UpdateChatMessageTemplateAction $updateAction,
        private readonly DeleteChatMessageTemplateAction $deleteAction,
        private readonly SyncMetaTemplatesAction $syncAction,
        private readonly CreateMetaTemplateAction $createMetaAction,
    ) {}

    /**
     * Listar todos os templates disponíveis para o tenant.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @return JsonResponse Lista paginada de templates.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', ChatMessageTemplate::class);

        $tenantId = $this->tenantId($request);
        $filters = $request->only(['search', 'status', 'chat_instance_id', 'provider', 'is_active', 'per_page']);
        $paginator = $this->actions->list($tenantId, $filters);
        $paginator->getCollection()->transform(fn ($item) => new ChatMessageTemplateResource($item));

        return $this->paginated($paginator);
    }

    /**
     * Exibir um template específico.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador do template.
     * @return JsonResponse Recurso serializado.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $template = ChatMessageTemplate::query()
            ->where('tenant_id', $this->tenantId($request))
            ->where('id', $id)
            ->firstOrFail();

        $this->authorize('view', $template);

        return $this->success(new ChatMessageTemplateResource($template));
    }

    /**
     * Criar um novo template de mensagem.
     *
     * @param  ChatMessageTemplateRequest  $request  Dados validados do template.
     * @return JsonResponse Recurso criado com status 201.
     */
    public function store(ChatMessageTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', ChatMessageTemplate::class);

        $tenantId = $this->tenantId($request);

        if ($request->input('provider') === 'meta') {
            $template = $this->createMetaAction->execute($tenantId, $request->validated());
        } else {
            $template = $this->actions->create($tenantId, $request->validated());
        }

        return $this->created(new ChatMessageTemplateResource($template), 'Template criado');
    }

    /**
     * Atualizar um template existente.
     *
     * @param  ChatMessageTemplateRequest  $request  Dados validados do template.
     * @param  string  $id  Identificador do template.
     * @return JsonResponse Recurso atualizado.
     */
    public function update(ChatMessageTemplateRequest $request, string $id): JsonResponse
    {
        $template = ChatMessageTemplate::query()
            ->where('tenant_id', $this->tenantId($request))
            ->where('id', $id)
            ->firstOrFail();

        $this->authorize('update', $template);

        $updated = $this->updateAction->execute($template, $request->validated());

        return $this->success(new ChatMessageTemplateResource($updated), 'Template atualizado');
    }

    /**
     * Remover um template de mensagem.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador do template.
     * @return JsonResponse Resposta 204.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $template = ChatMessageTemplate::query()
            ->where('tenant_id', $this->tenantId($request))
            ->where('id', $id)
            ->firstOrFail();

        $this->authorize('delete', $template);

        $this->deleteAction->execute($template);

        return $this->deleted();
    }

    /**
     * Sincronizar templates da Meta para o tenant.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @return JsonResponse Resultado da sincronização.
     */
    public function sync(Request $request): JsonResponse
    {
        $this->authorize('sync', ChatMessageTemplate::class);

        $tenantId = $this->tenantId($request);
        $chatInstanceId = $request->input('chat_instance_id');

        if (! is_string($chatInstanceId) || $chatInstanceId === '') {
            return $this->error('chat_instance_id é obrigatório.', 422);
        }

        $count = $this->syncAction->execute($tenantId, $chatInstanceId);

        return $this->success(['count' => $count], 'Templates sincronizados');
    }
}
