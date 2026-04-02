<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Controllers;

use Domain\Chat\Actions\ChatChatbotRuleActions;
use Domain\Chat\DTOs\ChatChatbotRuleDTO;
use Domain\Chat\Http\Requests\ChatChatbotRuleRequest;
use Domain\Chat\Http\Resources\ChatChatbotRuleResource;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador de Regras de Chatbot.
 *
 * Gerencia as regras de automação baseadas em texto (gatilhos e respostas),
 * permitindo configurar respostas automáticas simples para o atendimento.
 *
 * @category Controllers
 */
final class ChatChatbotRuleController extends BaseController
{
    public function __construct(
        private readonly ChatChatbotRuleActions $actions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Listar as regras de chatbot configuradas para o tenant.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @return JsonResponse Lista paginada de regras.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', \Domain\Chat\Models\ChatChatbotRule::class);

        $tenantId = (string) $request->user()->tenant_id;
        $paginator = $this->actions->list($tenantId);
        $paginator->getCollection()->transform(fn ($item) => new ChatChatbotRuleResource($item));

        return $this->paginated($paginator);
    }

    /**
     * Criar uma nova regra de automação.
     *
     * @param  ChatChatbotRuleRequest  $request  Dados validados (trigger_text, response_text, etc).
     * @return JsonResponse Registro da regra criada.
     */
    public function store(ChatChatbotRuleRequest $request): JsonResponse
    {
        $this->authorize('create', \Domain\Chat\Models\ChatChatbotRule::class);

        $tenantId = (string) $request->user()->tenant_id;
        $rule = $this->actions->create($tenantId, ChatChatbotRuleDTO::fromRequest($request));
        $this->audit->log($request->user(), $tenantId, 'chat.chatbot_rules.created', $rule);

        return $this->created(new ChatChatbotRuleResource($rule), 'Regra criada');
    }

    /**
     * Exibir detalhes de uma regra específica.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID da regra.
     * @return JsonResponse Objeto da regra de chatbot.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $rule = $this->actions->find($tenantId, $id);
        $this->authorize('view', $rule);

        return $this->success(new ChatChatbotRuleResource($rule), 'Regra carregada');
    }

    /**
     * Atualizar uma regra de chatbot existente.
     *
     * @param  ChatChatbotRuleRequest  $request  Dados validados.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Registro atualizado.
     */
    public function update(ChatChatbotRuleRequest $request, string $id): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $rule = $this->actions->find($tenantId, $id);
        $this->authorize('update', $rule);

        $rule = $this->actions->update($tenantId, $id, ChatChatbotRuleDTO::fromRequest($request));
        $this->audit->log($request->user(), $tenantId, 'chat.chatbot_rules.updated', $rule, $rule->getChanges());

        return $this->success(new ChatChatbotRuleResource($rule), 'Regra atualizada');
    }

    /**
     * Remover uma regra de chatbot.
     *
     * @param  Request  $request  Solicitação HTTP.
     * @param  string  $id  Identificador UUID.
     * @return JsonResponse Confirmação de exclusão.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $tenantId = (string) $request->user()->tenant_id;
        $rule = $this->actions->find($tenantId, $id);
        $this->authorize('delete', $rule);

        $this->actions->delete($tenantId, $id);
        $this->audit->log($request->user(), $tenantId, 'chat.chatbot_rules.deleted', $rule);

        return $this->deleted();
    }

    /**
     * Validar se uma palavra-chave está disponível para uso.
     *
     * Verifica se já existe outra regra com a mesma keyword/pattern no mesmo contexto
     * (tenant, instância, departamento).
     *
     * @param  Request  $request  Solicitação HTTP com parâmetros de validação.
     * @return JsonResponse Resultado da disponibilidade.
     */
    public function validateKeyword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'keyword' => 'required|string|max:500',
            'match_type' => 'nullable|string|in:exact,contains,starts_with,ends_with,regex',
            'instance_id' => 'nullable|uuid',
            'department_id' => 'nullable|uuid',
            'rule_id' => 'nullable|uuid',
        ]);

        $tenantId = (string) $request->user()->tenant_id;

        $available = $this->actions->isKeywordAvailable(
            tenantId: $tenantId,
            keyword: (string) $validated['keyword'],
            matchType: (string) ($validated['match_type'] ?? 'contains'),
            instanceId: $validated['instance_id'] ?? null,
            departmentId: $validated['department_id'] ?? null,
            ignoreRuleId: $validated['rule_id'] ?? null,
        );

        return $this->success([
            'available' => $available,
        ]);
    }
}
