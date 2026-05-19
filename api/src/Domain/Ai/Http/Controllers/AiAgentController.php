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
 * Controller de CRUD de agentes de IA.
 *
 * @category Controllers
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
     * List all AI agents for the tenant.
     *
     * @param  Request  $request  HTTP request with optional search filter.
     * @return JsonResponse Paginated list of agents.
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
     * Create a new AI agent.
     *
     * @param  AiAgentStoreRequest  $request  Validated agent data.
     * @return JsonResponse Created agent resource.
     */
    public function store(AiAgentStoreRequest $request): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $agent = $this->actions->create($tenantId, AiAgentDTO::fromRequest($request));

        return $this->created(new AiAgentResource($agent), 'Agente criado');
    }

    /**
     * Get a specific AI agent.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $id  UUID of the agent.
     * @return JsonResponse Agent resource.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $agent = $this->actions->find($tenantId, $id);

        return $this->success(new AiAgentResource($agent));
    }

    /**
     * Update an existing AI agent.
     *
     * @param  AiAgentUpdateRequest  $request  Validated agent data.
     * @param  string  $id  UUID of the agent.
     * @return JsonResponse Updated agent resource.
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
     * Delete an AI agent.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $id  UUID of the agent.
     * @return JsonResponse No content on success.
     */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $this->actions->delete($tenantId, $id);

        return $this->deleted();
    }

    /**
     * Toggle agent active status.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $id  UUID of the agent.
     * @return JsonResponse Updated agent with toggled status.
     */
    public function toggle(Request $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $agent = $this->actions->toggleActive($tenantId, $id);

        return $this->success(new AiAgentResource($agent), 'Status do agente atualizado');
    }

    /**
     * List all files associated with an agent.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $id  UUID of the agent.
     * @return JsonResponse Collection of agent files.
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
     * Get a specific agent file by slug.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $id  UUID of the agent.
     * @param  string  $slug  File slug identifier.
     * @return JsonResponse File contents and metadata.
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
     * Create or update an agent file.
     *
     * @param  Request  $request  HTTP request with 'content' field.
     * @param  string  $id  UUID of the agent.
     * @param  string  $slug  File slug identifier.
     * @return JsonResponse Updated file.
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
     * Get catalog of all available tools.
     *
     * @param  Request  $request  HTTP request.
     * @return JsonResponse List of available tools with definitions.
     */
    public function toolsCatalog(Request $request): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);

        return $this->success($this->toolDispatcher->getCatalog($tenantId));
    }

    /**
     * Get preset tools for a specific agent role.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $role  Agent role (e.g., 'general', 'seller').
     * @param  \Domain\Ai\Services\AiPermissionMatrixService  $matrix  Permission matrix service.
     * @return JsonResponse List of available tools for the role.
     */
    public function toolsPreset(Request $request, string $role, \Domain\Ai\Services\AiPermissionMatrixService $matrix): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $agentRole = \Domain\Ai\Enums\AiAgentRole::tryFrom($role) ?? \Domain\Ai\Enums\AiAgentRole::GENERAL;
        $tools = $matrix->getAvailableTools($agentRole);

        return $this->success(array_values(array_unique($tools)));
    }

    /**
     * List tools configured for an agent.
     *
     * Reads tool names from the ai_agent_tools pivot table via AiAgentToolPermissionService.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $id  UUID of the agent.
     * @return JsonResponse List of selected tools with metadata.
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
     * Update tools assigned to an agent.
     *
     * Synchronizes the ai_agent_tools pivot table via AiAgentToolPermissionService
     * and removes legacy metadata.tool_names if present.
     *
     * @param  AiAgentToolsUpdateRequest  $request  Validated request with tool_names.
     * @param  string  $id  UUID of the agent.
     * @return JsonResponse Updated list of selected tools.
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
     * List all triggers for an agent.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $id  UUID of the agent.
     * @return JsonResponse Collection of agent triggers.
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
     * Create a new trigger for an agent.
     *
     * @param  AiAgentTriggerStoreRequest  $request  Validated trigger data.
     * @param  string  $id  UUID of the agent.
     * @return JsonResponse Created trigger.
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
     * Update an existing trigger.
     *
     * @param  AiAgentTriggerStoreRequest  $request  Validated trigger data.
     * @param  string  $id  UUID of the agent.
     * @param  string  $triggerId  UUID of the trigger.
     * @return JsonResponse Updated trigger.
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
     * Delete a trigger.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $id  UUID of the agent.
     * @param  string  $triggerId  UUID of the trigger.
     * @return JsonResponse No content on success.
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
     * List all channels configured for an agent.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $id  UUID of the agent.
     * @return JsonResponse Collection of agent channels.
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
     * Create a new channel for an agent.
     *
     * @param  AiAgentChannelStoreRequest  $request  Validated channel data.
     * @param  string  $id  UUID of the agent.
     * @return JsonResponse Created channel.
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
     * Update an existing channel.
     *
     * @param  AiAgentChannelStoreRequest  $request  Validated channel data.
     * @param  string  $id  UUID of the agent.
     * @param  string  $channelId  UUID of the channel.
     * @return JsonResponse Updated channel.
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
     * Delete a channel.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $id  UUID of the agent.
     * @param  string  $channelId  UUID of the channel.
     * @return JsonResponse No content on success.
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
     * List all skills configured for an agent.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $id  UUID of the agent.
     * @return JsonResponse Collection of agent skills.
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
     * Create a new skill for an agent.
     *
     * @param  AiAgentSkillStoreRequest  $request  Validated skill data.
     * @param  string  $id  UUID of the agent.
     * @return JsonResponse Created skill.
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
     * Update an existing skill.
     *
     * @param  AiAgentSkillStoreRequest  $request  Validated skill data.
     * @param  string  $id  UUID of the agent.
     * @param  string  $skillId  UUID of the skill.
     * @return JsonResponse Updated skill.
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
     * Delete a skill.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $id  UUID of the agent.
     * @param  string  $skillId  UUID of the skill.
     * @return JsonResponse No content on success.
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
     * List all delegations configured for an agent.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $id  UUID of the agent (source).
     * @return JsonResponse Collection of agent delegations.
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
     * Create or update a delegation from one agent to another.
     *
     * @param  AiAgentDelegationStoreRequest  $request  Validated delegation data.
     * @param  string  $id  UUID of the source agent.
     * @return JsonResponse Upserted delegation.
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
     * Delete a delegation.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $id  UUID of the source agent.
     * @param  string  $delegationId  UUID of the delegation.
     * @return JsonResponse No content on success.
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
     * Get voice configuration for an agent.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $id  UUID of the agent.
     * @return JsonResponse Voice configuration (STT/TTS models, voice, speed).
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
     * Update voice configuration for an agent.
     *
     * @param  AiAgentVoiceUpdateRequest  $request  Validated voice config data.
     * @param  string  $id  UUID of the agent.
     * @return JsonResponse Updated agent with voice config.
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
