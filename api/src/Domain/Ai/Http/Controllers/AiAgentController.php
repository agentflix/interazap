<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Controllers;

use Domain\Ai\Actions\AiAgentActions;
use Domain\Ai\Actions\AiAgentSubresourceActions;
use Domain\Ai\DTOs\AiAgentDTO;
use Domain\Ai\Http\Requests\AiAgentChannelStoreRequest;
use Domain\Ai\Http\Requests\AiAgentDelegationStoreRequest;
use Domain\Ai\Http\Requests\AiAgentSkillStoreRequest;
use Domain\Ai\Http\Requests\AiAgentStoreRequest;
use Domain\Ai\Http\Requests\AiAgentToolsUpdateRequest;
use Domain\Ai\Http\Requests\AiAgentTriggerStoreRequest;
use Domain\Ai\Http\Requests\AiAgentUpdateRequest;
use Domain\Ai\Http\Requests\AiAgentVoiceUpdateRequest;
use Domain\Ai\Http\Resources\AiAgentResource;
use Domain\Ai\Models\AiAgentChannel;
use Domain\Ai\Models\AiAgentDelegation;
use Domain\Ai\Models\AiAgentFile;
use Domain\Ai\Models\AiAgentSkill;
use Domain\Ai\Models\AiAgentTrigger;
use Domain\Ai\Services\AiAgentToolPermissionService;
use Domain\Ai\Services\ToolDispatcherService;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Controller para gerenciamento de agentes de IA do módulo de IA.
 */
final class AiAgentController extends BaseController
{
    public function __construct(
        private readonly AiAgentActions $actions,
        private readonly AiAgentSubresourceActions $subresourceActions,
        private readonly ToolDispatcherService $toolDispatcher,
        private readonly AiAgentToolPermissionService $toolPermissionService,
    ) {}

    /**
     * Lista todos os agentes de IA do tenant.
     *
     * @param  Request  $request  Requisição HTTP com filtro de busca opcional.
     * @return JsonResponse Lista paginada de agentes.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $search = $request->string('search')->toString();
        $paginator = $this->actions->list($tenantId, $search !== '' ? $search : null);
        $paginator->getCollection()->transform(fn ($item) => new AiAgentResource($item));

        return $this->paginated($paginator);
    }

    /**
     * Cria um novo agente de IA.
     *
     * @param  AiAgentStoreRequest  $request  Dados validados do agente.
     * @return JsonResponse Agente criado.
     */
    public function store(AiAgentStoreRequest $request): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $agent = $this->actions->create($tenantId, AiAgentDTO::fromRequest($request));

        return $this->created(new AiAgentResource($agent), 'Agente criado');
    }

    /**
     * Exibe um agente de IA específico.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  UUID do agente.
     * @return JsonResponse Dados do agente.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $agent = $this->actions->find($tenantId, $id);

        return $this->success(new AiAgentResource($agent));
    }

    /**
     * Atualiza um agente de IA existente.
     *
     * @param  AiAgentUpdateRequest  $request  Dados validados do agente.
     * @param  string  $id  UUID do agente.
     * @return JsonResponse Agente atualizado.
     */
    public function update(AiAgentUpdateRequest $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $current = $this->actions->find($tenantId, $id);
        $merged = array_merge($current->toArray(), $request->validated());
        $agent = $this->actions->update($tenantId, $id, AiAgentDTO::fromArray($merged));

        return $this->success(new AiAgentResource($agent), 'Agente atualizado');
    }

    /**
     * Remove um agente de IA.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  UUID do agente.
     * @return JsonResponse Sem conteúdo em caso de sucesso.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $this->actions->delete($tenantId, $id);

        return $this->deleted();
    }

    /**
     * Alterna o status ativo/inativo do agente.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  UUID do agente.
     * @return JsonResponse Agente com status atualizado.
     */
    public function toggle(Request $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $agent = $this->actions->toggleActive($tenantId, $id);

        return $this->success(new AiAgentResource($agent), 'Status do agente atualizado');
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
        $this->actions->find($tenantId, $id);

        $files = AiAgentFile::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $id)
            ->orderBy('slug')
            ->get();

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
        $this->actions->find($tenantId, $id);

        $file = AiAgentFile::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $id)
            ->where('slug', $slug)
            ->firstOrFail();

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
        $this->actions->find($tenantId, $id);

        $file = AiAgentFile::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'agent_id' => $id,
                'slug' => $slug,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'content' => $validated['content'] ?? null,
                'updated_by' => (string) optional($request->user())->id,
            ],
        );

        return $this->success($file, 'Arquivo do agente atualizado');
    }

    /**
     * Lista o catálogo de todas as ferramentas disponíveis.
     *
     * @param  Request  $request  Requisição HTTP.
     * @return JsonResponse Lista de ferramentas disponíveis com definições.
     */
    public function toolsCatalog(Request $request): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);

        return $this->success($this->toolDispatcher->getCatalog($tenantId));
    }

    /**
     * Lista ferramentas predefinidas para um papel de agente específico.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $role  Papel do agente (ex.: 'general', 'seller').
     * @param  \Domain\Ai\Services\AiPermissionMatrixService  $matrix  Serviço de matriz de permissões.
     * @return JsonResponse Lista de ferramentas disponíveis para o papel.
     */
    public function toolsPreset(Request $request, string $role, \Domain\Ai\Services\AiPermissionMatrixService $matrix): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $agentRole = \Domain\Ai\Enums\AiAgentRole::tryFrom($role) ?? \Domain\Ai\Enums\AiAgentRole::GENERAL;
        $tools = $matrix->getAvailableTools($agentRole);

        return $this->success(array_values(array_unique($tools)));
    }

    /**
     * Lista as ferramentas configuradas para um agente.
     *
     * Lê os nomes das ferramentas da tabela pivot ai_agent_tools via AiAgentToolPermissionService.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  UUID do agente.
     * @return JsonResponse Lista de ferramentas selecionadas com metadados.
     */
    public function tools(Request $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $agent = $this->actions->find($tenantId, $id);

        $selectedToolNames = $this->toolPermissionService->toolNamesForAgent($tenantId, (string) $agent->id);

        $catalog = $this->toolDispatcher->getCatalog($tenantId);
        $tools = $this->subresourceActions->mapSelectedToolsToLinks($catalog, $selectedToolNames);

        return $this->success($tools);
    }

    /**
     * Atualiza as ferramentas atribuídas a um agente.
     *
     * Sincroniza a tabela pivot ai_agent_tools via AiAgentToolPermissionService
     * e remove o legacy metadata.tool_names caso presente.
     *
     * @param  AiAgentToolsUpdateRequest  $request  Requisição validada com tool_names.
     * @param  string  $id  UUID do agente.
     * @return JsonResponse Lista atualizada de ferramentas selecionadas.
     */
    public function toolsUpdate(AiAgentToolsUpdateRequest $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $agent = $this->actions->find($tenantId, $id);
        $validated = $request->validated();

        $requestedToolNames = (array) ($validated['tool_names'] ?? []);
        $catalog = $this->toolDispatcher->getCatalog($tenantId);
        $availableToolNames = array_map(
            static fn (array $tool): string => (string) data_get($tool, 'name', ''),
            $catalog,
        );

        $toolNames = array_values(array_filter(array_unique(array_map(
            static fn (mixed $tool): string => trim((string) $tool),
            $requestedToolNames,
        )), static fn (string $tool): bool => in_array($tool, $availableToolNames, true)));

        // Sincroniza o pivot via serviço dedicado
        $this->toolPermissionService->syncAgentTools($tenantId, (string) $agent->id, $toolNames);

        // Remove legacy metadata.tool_names se presente
        $metadata = is_array($agent->metadata) ? $agent->metadata : [];
        if (array_key_exists('tool_names', $metadata)) {
            unset($metadata['tool_names']);
            $agent->metadata = $metadata;
            $agent->save();
        }

        // Invalida cache de tool definitions do InternalAiController
        Cache::forget(sprintf('internal:ai:tools:%s', $id));

        $selectedTools = $this->subresourceActions->mapSelectedToolsToLinks($catalog, $toolNames);

        return $this->success($selectedTools, 'Ferramentas do agente atualizadas');
    }

    /**
     * Lista todos os gatilhos configurados para um agente.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  string  $id  UUID do agente.
     * @return JsonResponse Coleção de gatilhos do agente.
     */
    public function triggers(Request $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $this->actions->find($tenantId, $id);

        $triggers = AiAgentTrigger::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $id)
            ->latest()
            ->get();

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
        $this->actions->find($tenantId, $id);

        $trigger = AiAgentTrigger::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'agent_id' => $id,
            ...$request->validated(),
        ]);

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
        $this->actions->find($tenantId, $id);

        $trigger = AiAgentTrigger::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $id)
            ->findOrFail($triggerId);

        $trigger->fill($request->validated());
        $trigger->save();

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
        $this->actions->find($tenantId, $id);

        $trigger = AiAgentTrigger::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $id)
            ->findOrFail($triggerId);
        $trigger->delete();

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
        $this->actions->find($tenantId, $id);

        $channels = AiAgentChannel::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $id)
            ->orderBy('channel')
            ->orderBy('external_ref')
            ->get();

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
        $this->actions->find($tenantId, $id);

        $channel = AiAgentChannel::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'agent_id' => $id,
            ...$request->validated(),
        ]);

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
        $this->actions->find($tenantId, $id);

        $channel = AiAgentChannel::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $id)
            ->findOrFail($channelId);

        $channel->fill($request->validated());
        $channel->save();

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
        $this->actions->find($tenantId, $id);

        $channel = AiAgentChannel::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $id)
            ->findOrFail($channelId);
        $channel->delete();

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
        $this->actions->find($tenantId, $id);

        $skills = AiAgentSkill::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $id)
            ->orderBy('name')
            ->get();

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
        $this->actions->find($tenantId, $id);

        $skill = AiAgentSkill::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'agent_id' => $id,
            ...$request->validated(),
        ]);

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
        $this->actions->find($tenantId, $id);

        $skill = AiAgentSkill::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $id)
            ->findOrFail($skillId);

        $skill->fill($request->validated());
        $skill->save();

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
        $this->actions->find($tenantId, $id);

        $skill = AiAgentSkill::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $id)
            ->findOrFail($skillId);
        $skill->delete();

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
        $this->actions->find($tenantId, $id);

        $delegations = AiAgentDelegation::query()
            ->where('tenant_id', $tenantId)
            ->where('source_agent_id', $id)
            ->with('targetAgent:id,name')
            ->latest()
            ->get();

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
        $this->actions->find($tenantId, $id);

        $targetAgentId = (string) $request->validated('target_agent_id');
        $this->actions->find($tenantId, $targetAgentId);

        if ($targetAgentId === $id) {
            return $this->error('Um agente não pode delegar para si mesmo.', 422);
        }

        $delegation = AiAgentDelegation::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'source_agent_id' => $id,
                'target_agent_id' => $targetAgentId,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'max_depth' => (int) $request->validated('max_depth', 1),
                'is_active' => (bool) $request->validated('is_active', true),
                'metadata' => $request->validated('metadata'),
            ],
        );

        return $this->success($delegation->load('targetAgent:id,name'), 'Delegação atualizada');
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
        $this->actions->find($tenantId, $id);

        $delegation = AiAgentDelegation::query()
            ->where('tenant_id', $tenantId)
            ->where('source_agent_id', $id)
            ->findOrFail($delegationId);
        $delegation->delete();

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
        $agent = $this->actions->find($tenantId, $id);

        return $this->success([
            'voice_response_mode' => $agent->voice_response_mode,
            'stt_model' => $agent->stt_model,
            'stt_language' => $agent->stt_language,
            'tts_model' => $agent->tts_model,
            'tts_voice' => $agent->tts_voice,
            'tts_speed' => $agent->tts_speed,
        ]);
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
        $agent = $this->actions->find($tenantId, $id);
        $agent->fill($request->validated());
        $agent->save();

        return $this->success(new AiAgentResource($agent), 'Configuração de voz atualizada');
    }
}
