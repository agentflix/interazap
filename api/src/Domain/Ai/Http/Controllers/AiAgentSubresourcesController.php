<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Controllers;

use Domain\Ai\Actions\AiAgentSubresourceActions;
use Domain\Ai\Enums\AiAgentRole;
use Domain\Ai\Http\Requests\AiAgentChannelStoreRequest;
use Domain\Ai\Http\Requests\AiAgentDelegationStoreRequest;
use Domain\Ai\Http\Requests\AiAgentSkillStoreRequest;
use Domain\Ai\Http\Requests\AiAgentToolsUpdateRequest;
use Domain\Ai\Http\Requests\AiAgentTriggerStoreRequest;
use Domain\Ai\Http\Requests\AiAgentVoiceUpdateRequest;
use Domain\Ai\Http\Resources\AiAgentResource;
use Domain\Ai\Services\AiPermissionMatrixService;
use Domain\Ai\Services\ToolDispatcherService;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller para sub-recursos de agentes de IA do módulo de IA.
 *
 * Gerencia arquivos, ferramentas, gatilhos, canais, habilidades, delegações
 * e configurações de voz dos agentes.
 */
final class AiAgentSubresourcesController extends BaseController
{
    public function __construct(
        private readonly AiAgentSubresourceActions $subresourceActions,
        private readonly ToolDispatcherService $toolDispatcher,
    ) {}

    /**
     * Lista o catálogo de todas as ferramentas disponíveis.
     *
     * @param  Request  $request  Requisição HTTP.
     * @return JsonResponse Lista de ferramentas disponíveis com definições.
     */
    public function toolsCatalog(Request $request): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        return $this->success($this->toolDispatcher->getCatalog($this->tenantId($request)));
    }

    /**
     * Lista ferramentas predefinidas para um papel de agente específico.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $role  Papel do agente (ex.: 'general', 'seller').
     * @param  AiPermissionMatrixService  $matrix  Serviço de matriz de permissões.
     * @return JsonResponse Lista de ferramentas disponíveis para o papel.
     */
    public function toolsPreset(Request $request, string $role, AiPermissionMatrixService $matrix): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $agentRole = AiAgentRole::tryFrom($role) ?? AiAgentRole::GENERAL;
        $tools = $matrix->getAvailableTools($agentRole);

        return $this->success(array_values(array_unique($tools)));
    }

    /**
     * Lista todos os arquivos associados a um agente.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  UUID do agente.
     * @return JsonResponse Coleção de arquivos do agente.
     */
    public function files(Request $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $files = $this->subresourceActions->listFiles($tenantId, $id);

        return $this->success($files);
    }

    /**
     * Exibe um arquivo específico do agente pelo slug.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  UUID do agente.
     * @param  string  $slug  Identificador slug do arquivo.
     * @return JsonResponse Conteúdo e metadados do arquivo.
     */
    public function fileShow(Request $request, string $id, string $slug): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $file = $this->subresourceActions->findFile($tenantId, $id, $slug);

        return $this->success($file);
    }

    /**
     * Cria ou atualiza um arquivo do agente.
     *
     * @param  Request  $request  Requisição HTTP com campo 'content'.
     * @param  string  $id  UUID do agente.
     * @param  string  $slug  Identificador slug do arquivo.
     * @return JsonResponse Arquivo atualizado.
     */
    public function fileUpdate(Request $request, string $id, string $slug): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $validated = $request->validate([
            'content' => ['nullable', 'string'],
        ]);

        $tenantId = $this->tenantId($request);
        $file = $this->subresourceActions->upsertFile(
            $tenantId,
            $id,
            $slug,
            $validated['content'] ?? null,
            optional($request->user())->id ? (string) optional($request->user())->id : null,
        );

        return $this->success($file, 'Arquivo do agente atualizado');
    }

    /**
     * Lista as ferramentas configuradas para um agente.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  UUID do agente.
     * @return JsonResponse Lista de ferramentas selecionadas com metadados.
     */
    public function tools(Request $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $tools = $this->subresourceActions->listTools($tenantId, $id);

        return $this->success($tools);
    }

    /**
     * Atualiza as ferramentas atribuídas a um agente.
     *
     * @param  AiAgentToolsUpdateRequest  $request  Requisição validada com tool_names ou tool_ids.
     * @param  string  $id  UUID do agente.
     * @return JsonResponse Lista atualizada de ferramentas selecionadas.
     */
    public function toolsUpdate(AiAgentToolsUpdateRequest $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $selectedTools = $this->subresourceActions->updateTools($tenantId, $id, $request->validated());

        return $this->success($selectedTools, 'Ferramentas do agente atualizadas');
    }

    /**
     * Lista todos os gatilhos de um agente.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  UUID do agente.
     * @return JsonResponse Coleção de gatilhos do agente.
     */
    public function triggers(Request $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $triggers = $this->subresourceActions->listTriggers($tenantId, $id);

        return $this->success($triggers);
    }

    /**
     * Cria um novo gatilho para um agente.
     *
     * @param  AiAgentTriggerStoreRequest  $request  Dados validados do gatilho.
     * @param  string  $id  UUID do agente.
     * @return JsonResponse Gatilho criado.
     */
    public function triggerStore(AiAgentTriggerStoreRequest $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $trigger = $this->subresourceActions->createTrigger($tenantId, $id, $request->validated());

        return $this->created($trigger, 'Trigger criado');
    }

    /**
     * Atualiza um gatilho existente.
     *
     * @param  AiAgentTriggerStoreRequest  $request  Dados validados do gatilho.
     * @param  string  $id  UUID do agente.
     * @param  string  $triggerId  UUID do gatilho.
     * @return JsonResponse Gatilho atualizado.
     */
    public function triggerUpdate(AiAgentTriggerStoreRequest $request, string $id, string $triggerId): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $trigger = $this->subresourceActions->updateTrigger($tenantId, $id, $triggerId, $request->validated());

        return $this->success($trigger, 'Trigger atualizado');
    }

    /**
     * Remove um gatilho.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  UUID do agente.
     * @param  string  $triggerId  UUID do gatilho.
     * @return JsonResponse Sem conteúdo em caso de sucesso.
     */
    public function triggerDestroy(Request $request, string $id, string $triggerId): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $this->subresourceActions->deleteTrigger($tenantId, $id, $triggerId);

        return $this->deleted();
    }

    /**
     * Lista todos os canais configurados para um agente.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  UUID do agente.
     * @return JsonResponse Coleção de canais do agente.
     */
    public function channels(Request $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $channels = $this->subresourceActions->listChannels($tenantId, $id);

        return $this->success($channels);
    }

    /**
     * Cria um novo canal para um agente.
     *
     * @param  AiAgentChannelStoreRequest  $request  Dados validados do canal.
     * @param  string  $id  UUID do agente.
     * @return JsonResponse Canal criado.
     */
    public function channelStore(AiAgentChannelStoreRequest $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $channel = $this->subresourceActions->createChannel($tenantId, $id, $request->validated());

        return $this->created($channel, 'Canal criado');
    }

    /**
     * Atualiza um canal existente.
     *
     * @param  AiAgentChannelStoreRequest  $request  Dados validados do canal.
     * @param  string  $id  UUID do agente.
     * @param  string  $channelId  UUID do canal.
     * @return JsonResponse Canal atualizado.
     */
    public function channelUpdate(AiAgentChannelStoreRequest $request, string $id, string $channelId): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $channel = $this->subresourceActions->updateChannel($tenantId, $id, $channelId, $request->validated());

        return $this->success($channel, 'Canal atualizado');
    }

    /**
     * Remove um canal.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  UUID do agente.
     * @param  string  $channelId  UUID do canal.
     * @return JsonResponse Sem conteúdo em caso de sucesso.
     */
    public function channelDestroy(Request $request, string $id, string $channelId): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $this->subresourceActions->deleteChannel($tenantId, $id, $channelId);

        return $this->deleted();
    }

    /**
     * Lista todas as habilidades configuradas para um agente.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  UUID do agente.
     * @return JsonResponse Coleção de habilidades do agente.
     */
    public function skills(Request $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $skills = $this->subresourceActions->listSkills($tenantId, $id);

        return $this->success($skills);
    }

    /**
     * Cria uma nova habilidade para um agente.
     *
     * @param  AiAgentSkillStoreRequest  $request  Dados validados da habilidade.
     * @param  string  $id  UUID do agente.
     * @return JsonResponse Habilidade criada.
     */
    public function skillStore(AiAgentSkillStoreRequest $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $skill = $this->subresourceActions->createSkill($tenantId, $id, $request->validated());

        return $this->created($skill, 'Skill criada');
    }

    /**
     * Atualiza uma habilidade existente.
     *
     * @param  AiAgentSkillStoreRequest  $request  Dados validados da habilidade.
     * @param  string  $id  UUID do agente.
     * @param  string  $skillId  UUID da habilidade.
     * @return JsonResponse Habilidade atualizada.
     */
    public function skillUpdate(AiAgentSkillStoreRequest $request, string $id, string $skillId): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $skill = $this->subresourceActions->updateSkill($tenantId, $id, $skillId, $request->validated());

        return $this->success($skill, 'Skill atualizada');
    }

    /**
     * Remove uma habilidade.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  UUID do agente.
     * @param  string  $skillId  UUID da habilidade.
     * @return JsonResponse Sem conteúdo em caso de sucesso.
     */
    public function skillDestroy(Request $request, string $id, string $skillId): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $this->subresourceActions->deleteSkill($tenantId, $id, $skillId);

        return $this->deleted();
    }

    /**
     * Lista todas as delegações configuradas para um agente.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  UUID do agente de origem.
     * @return JsonResponse Coleção de delegações do agente.
     */
    public function delegations(Request $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $delegations = $this->subresourceActions->listDelegations($tenantId, $id);

        return $this->success($delegations);
    }

    /**
     * Cria ou atualiza uma delegação de um agente para outro.
     *
     * @param  AiAgentDelegationStoreRequest  $request  Dados validados da delegação.
     * @param  string  $id  UUID do agente de origem.
     * @return JsonResponse Delegação criada ou atualizada.
     */
    public function delegationStore(AiAgentDelegationStoreRequest $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $targetAgentId = (string) $request->validated('target_agent_id');

        if ($targetAgentId === $id) {
            return $this->error('Um agente não pode delegar para si mesmo.', 422);
        }

        $delegation = $this->subresourceActions->upsertDelegation($tenantId, $id, $request->validated());

        return $this->success($delegation, 'Delegação atualizada');
    }

    /**
     * Remove uma delegação.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  UUID do agente de origem.
     * @param  string  $delegationId  UUID da delegação.
     * @return JsonResponse Sem conteúdo em caso de sucesso.
     */
    public function delegationDestroy(Request $request, string $id, string $delegationId): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $this->subresourceActions->deleteDelegation($tenantId, $id, $delegationId);

        return $this->deleted();
    }

    /**
     * Obtém a configuração de voz de um agente.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  UUID do agente.
     * @return JsonResponse Configuração de voz (modelos STT/TTS, voz, velocidade).
     */
    public function voice(Request $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);

        return $this->success($this->subresourceActions->voiceConfig($tenantId, $id));
    }

    /**
     * Atualiza a configuração de voz de um agente.
     *
     * @param  AiAgentVoiceUpdateRequest  $request  Dados validados da configuração de voz.
     * @param  string  $id  UUID do agente.
     * @return JsonResponse Agente atualizado com configuração de voz.
     */
    public function voiceUpdate(AiAgentVoiceUpdateRequest $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $agent = $this->subresourceActions->updateVoice($tenantId, $id, $request->validated());

        return $this->success(new AiAgentResource($agent), 'Configuração de voz atualizada');
    }
}
