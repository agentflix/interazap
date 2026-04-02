<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Actions\ChatMessageTemplateActions;
use Domain\Chat\Http\Requests\ChatMessageTemplateRequest;
use Domain\Chat\Http\Resources\ChatMessageTemplateResource;
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
    public function __construct(private readonly ChatMessageTemplateActions $actions) {}

    /**
     * Listar todos os templates disponíveis para o tenant.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @return JsonResponse Lista paginada de templates.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \Domain\Chat\Models\ChatMessageTemplate::class);

        $tenantId = $this->tenantId($request);
        $paginator = $this->actions->list($tenantId);
        $paginator->getCollection()->transform(fn ($item) => new ChatMessageTemplateResource($item));

        return $this->paginated($paginator);
    }

    /**
     * Criar um novo template de mensagem.
     *
     * @param  ChatMessageTemplateRequest  $request  Dados validados do template.
     * @return JsonResponse Recurso criado com status 201.
     */
    public function store(ChatMessageTemplateRequest $request): JsonResponse
    {
        $this->authorize('create', \Domain\Chat\Models\ChatMessageTemplate::class);

        $tenantId = $this->tenantId($request);
        $template = $this->actions->create($tenantId, $request->validated());

        return $this->created(new ChatMessageTemplateResource($template), 'Template criado');
    }
}
