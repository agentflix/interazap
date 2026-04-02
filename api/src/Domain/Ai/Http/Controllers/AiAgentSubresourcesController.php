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
 * Controller for AI agent sub-resources and related catalogs.
 *
 * Handles files, tools, triggers, channels, skills, delegations,
 * and voice configuration for AI agents.
 *
 * @category Controllers
 */
final class AiAgentSubresourcesController extends BaseController
{
    public function __construct(
        private readonly AiAgentSubresourceActions $subresourceActions,
        private readonly ToolDispatcherService $toolDispatcher,
    ) {}

    /**
     * Get catalog of all available tools.
     *
     * @param  Request  $request  HTTP request.
     * @return JsonResponse List of available tools with definitions.
     */
    public function toolsCatalog(Request $request): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        return $this->success($this->toolDispatcher->getCatalog());
    }

    /**
     * Get preset tools for a specific agent role.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $role  Agent role (e.g., 'general', 'seller').
     * @param  AiPermissionMatrixService  $matrix  Permission matrix service.
     * @return JsonResponse List of available tools for the role.
     */
    public function toolsPreset(Request $request, string $role, AiPermissionMatrixService $matrix): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $agentRole = AiAgentRole::tryFrom($role) ?? AiAgentRole::GENERAL;
        $tools = $matrix->getAvailableTools($agentRole);

        return $this->success(array_values(array_unique($tools)));
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
        $files = $this->subresourceActions->listFiles($tenantId, $id);

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
        $file = $this->subresourceActions->findFile($tenantId, $id, $slug);

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
     * List tools configured for an agent.
     *
     * @param  Request  $request  HTTP request.
     * @param  string  $id  UUID of the agent.
     * @return JsonResponse List of selected tools with metadata.
     */
    public function tools(Request $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $tools = $this->subresourceActions->listTools($tenantId, $id);

        return $this->success($tools);
    }

    /**
     * Update tools assigned to an agent.
     *
     * @param  AiAgentToolsUpdateRequest  $request  Validated request with tool_names or tool_ids.
     * @param  string  $id  UUID of the agent.
     * @return JsonResponse Updated list of selected tools.
     */
    public function toolsUpdate(AiAgentToolsUpdateRequest $request, string $id): JsonResponse
    {
        $this->authorize('ai.autopilots.manage');

        $tenantId = $this->tenantId($request);
        $selectedTools = $this->subresourceActions->updateTools($tenantId, $id, $request->validated());

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
        $triggers = $this->subresourceActions->listTriggers($tenantId, $id);

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
        $trigger = $this->subresourceActions->createTrigger($tenantId, $id, $request->validated());

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
        $trigger = $this->subresourceActions->updateTrigger($tenantId, $id, $triggerId, $request->validated());

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
        $this->subresourceActions->deleteTrigger($tenantId, $id, $triggerId);

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
        $channels = $this->subresourceActions->listChannels($tenantId, $id);

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
        $channel = $this->subresourceActions->createChannel($tenantId, $id, $request->validated());

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
        $channel = $this->subresourceActions->updateChannel($tenantId, $id, $channelId, $request->validated());

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
        $this->subresourceActions->deleteChannel($tenantId, $id, $channelId);

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
        $skills = $this->subresourceActions->listSkills($tenantId, $id);

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
        $skill = $this->subresourceActions->createSkill($tenantId, $id, $request->validated());

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
        $skill = $this->subresourceActions->updateSkill($tenantId, $id, $skillId, $request->validated());

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
        $this->subresourceActions->deleteSkill($tenantId, $id, $skillId);

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
        $delegations = $this->subresourceActions->listDelegations($tenantId, $id);

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
        $targetAgentId = (string) $request->validated('target_agent_id');

        if ($targetAgentId === $id) {
            return $this->error('Um agente não pode delegar para si mesmo.', 422);
        }

        $delegation = $this->subresourceActions->upsertDelegation($tenantId, $id, $request->validated());

        return $this->success($delegation, 'Delegação atualizada');
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
        $this->subresourceActions->deleteDelegation($tenantId, $id, $delegationId);

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

        return $this->success($this->subresourceActions->voiceConfig($tenantId, $id));
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
        $agent = $this->subresourceActions->updateVoice($tenantId, $id, $request->validated());

        return $this->success(new AiAgentResource($agent), 'Configuração de voz atualizada');
    }
}
