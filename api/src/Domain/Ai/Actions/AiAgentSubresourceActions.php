<?php

declare(strict_types=1);

namespace Domain\Ai\Actions;

use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAgentChannel;
use Domain\Ai\Models\AiAgentDelegation;
use Domain\Ai\Models\AiAgentFile;
use Domain\Ai\Models\AiAgentSkill;
use Domain\Ai\Models\AiAgentTrigger;
use Domain\Ai\Services\ToolDispatcherService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

/**
 * Handles Ai agent subresources without changing controller contracts.
 */
final class AiAgentSubresourceActions
{
    /**
     * __construct.
     */
    public function __construct(
        private readonly AiAgentActions $agentActions,
        private readonly ToolDispatcherService $toolDispatcher,
    ) {}

    /**
     * @return Collection<int, AiAgentFile>
     */
    public function listFiles(string $tenantId, string $agentId): Collection
    {
        $this->findAgent($tenantId, $agentId);

        return AiAgentFile::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $agentId)
            ->orderBy('slug')
            ->get();
    }

    /**
     * Localizar file.
     */
    public function findFile(string $tenantId, string $agentId, string $slug): AiAgentFile
    {
        $this->findAgent($tenantId, $agentId);

        return AiAgentFile::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $agentId)
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * Upsert file.
     */
    public function upsertFile(string $tenantId, string $agentId, string $slug, ?string $content, ?string $userId): AiAgentFile
    {
        $this->findAgent($tenantId, $agentId);

        return AiAgentFile::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'agent_id' => $agentId,
                'slug' => $slug,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'content' => $content,
                'updated_by' => $userId,
            ],
        );
    }

    /**
     * @return list<array<string, string>>
     */
    public function listTools(string $tenantId, string $agentId): array
    {
        $agent = $this->findAgent($tenantId, $agentId);
        $metadata = is_array($agent->metadata) ? $agent->metadata : [];
        $selectedTools = data_get($metadata, 'tool_names');
        $selectedToolNames = is_array($selectedTools)
            ? array_values(array_map(static fn (mixed $item): string => (string) $item, $selectedTools))
            : [];

        return $this->mapSelectedToolsToLinks(
            $this->toolDispatcher->getCatalog((string) $agent->role),
            $selectedToolNames,
        );
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return list<array<string, string>>
     */
    public function updateTools(string $tenantId, string $agentId, array $validated): array
    {
        $agent = $this->findAgent($tenantId, $agentId);

        $requestedToolNames = (array) ($validated['tool_names'] ?? $validated['tool_ids'] ?? []);
        $catalog = $this->toolDispatcher->getCatalog((string) $agent->role);
        $availableToolNames = array_map(
            static fn (array $tool): string => (string) data_get($tool, 'name', ''),
            $catalog,
        );

        $toolNames = array_values(array_filter(array_unique(array_map(
            static fn (mixed $tool): string => trim((string) $tool),
            $requestedToolNames,
        )), static fn (string $tool): bool => in_array($tool, $availableToolNames, true)));

        $metadata = is_array($agent->metadata) ? $agent->metadata : [];
        $metadata['tool_names'] = $toolNames;
        $agent->metadata = $metadata;
        $agent->save();

        return $this->mapSelectedToolsToLinks($catalog, $toolNames);
    }

    /**
     * @return Collection<int, AiAgentTrigger>
     */
    public function listTriggers(string $tenantId, string $agentId): Collection
    {
        $this->findAgent($tenantId, $agentId);

        return AiAgentTrigger::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $agentId)
            ->latest()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createTrigger(string $tenantId, string $agentId, array $attributes): AiAgentTrigger
    {
        $this->findAgent($tenantId, $agentId);

        return AiAgentTrigger::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateTrigger(string $tenantId, string $agentId, string $triggerId, array $attributes): AiAgentTrigger
    {
        $trigger = $this->findTrigger($tenantId, $agentId, $triggerId);
        $trigger->fill($attributes);
        $trigger->save();

        return $trigger;
    }

    /**
     * Excluir trigger.
     */
    public function deleteTrigger(string $tenantId, string $agentId, string $triggerId): void
    {
        $this->findTrigger($tenantId, $agentId, $triggerId)->delete();
    }

    /**
     * @return Collection<int, AiAgentChannel>
     */
    public function listChannels(string $tenantId, string $agentId): Collection
    {
        $this->findAgent($tenantId, $agentId);

        return AiAgentChannel::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $agentId)
            ->orderBy('channel')
            ->orderBy('external_ref')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createChannel(string $tenantId, string $agentId, array $attributes): AiAgentChannel
    {
        $this->findAgent($tenantId, $agentId);

        return AiAgentChannel::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateChannel(string $tenantId, string $agentId, string $channelId, array $attributes): AiAgentChannel
    {
        $channel = $this->findChannel($tenantId, $agentId, $channelId);
        $channel->fill($attributes);
        $channel->save();

        return $channel;
    }

    /**
     * Excluir channel.
     */
    public function deleteChannel(string $tenantId, string $agentId, string $channelId): void
    {
        $this->findChannel($tenantId, $agentId, $channelId)->delete();
    }

    /**
     * @return Collection<int, AiAgentSkill>
     */
    public function listSkills(string $tenantId, string $agentId): Collection
    {
        $this->findAgent($tenantId, $agentId);

        return AiAgentSkill::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $agentId)
            ->orderBy('name')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createSkill(string $tenantId, string $agentId, array $attributes): AiAgentSkill
    {
        $this->findAgent($tenantId, $agentId);

        return AiAgentSkill::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'agent_id' => $agentId,
            ...$attributes,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateSkill(string $tenantId, string $agentId, string $skillId, array $attributes): AiAgentSkill
    {
        $skill = $this->findSkill($tenantId, $agentId, $skillId);
        $skill->fill($attributes);
        $skill->save();

        return $skill;
    }

    /**
     * Excluir skill.
     */
    public function deleteSkill(string $tenantId, string $agentId, string $skillId): void
    {
        $this->findSkill($tenantId, $agentId, $skillId)->delete();
    }

    /**
     * @return Collection<int, AiAgentDelegation>
     */
    public function listDelegations(string $tenantId, string $agentId): Collection
    {
        $this->findAgent($tenantId, $agentId);

        return AiAgentDelegation::query()
            ->where('tenant_id', $tenantId)
            ->where('source_agent_id', $agentId)
            ->with('targetAgent:id,name')
            ->latest()
            ->get();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertDelegation(string $tenantId, string $agentId, array $attributes): AiAgentDelegation
    {
        $this->findAgent($tenantId, $agentId);

        $targetAgentId = (string) ($attributes['target_agent_id'] ?? '');
        $this->findAgent($tenantId, $targetAgentId);

        return AiAgentDelegation::query()->updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'source_agent_id' => $agentId,
                'target_agent_id' => $targetAgentId,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'max_depth' => (int) ($attributes['max_depth'] ?? 1),
                'is_active' => (bool) ($attributes['is_active'] ?? true),
                'metadata' => $attributes['metadata'] ?? null,
            ],
        )->load('targetAgent:id,name');
    }

    /**
     * Excluir delegation.
     */
    public function deleteDelegation(string $tenantId, string $agentId, string $delegationId): void
    {
        AiAgentDelegation::query()
            ->where('tenant_id', $tenantId)
            ->where('source_agent_id', $agentId)
            ->findOrFail($delegationId)
            ->delete();
    }

    /**
     * @return array<string, mixed>
     */
    public function voiceConfig(string $tenantId, string $agentId): array
    {
        $agent = $this->findAgent($tenantId, $agentId);

        return [
            'voice_response_mode' => $agent->voice_response_mode,
            'stt_model' => $agent->stt_model,
            'stt_language' => $agent->stt_language,
            'tts_model' => $agent->tts_model,
            'tts_voice' => $agent->tts_voice,
            'tts_speed' => $agent->tts_speed,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateVoice(string $tenantId, string $agentId, array $attributes): AiAgent
    {
        $agent = $this->findAgent($tenantId, $agentId);
        $agent->fill($attributes);
        $agent->save();

        return $agent;
    }

    private function findAgent(string $tenantId, string $agentId): AiAgent
    {
        return $this->agentActions->find($tenantId, $agentId);
    }

    private function findTrigger(string $tenantId, string $agentId, string $triggerId): AiAgentTrigger
    {
        $this->findAgent($tenantId, $agentId);

        return AiAgentTrigger::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $agentId)
            ->findOrFail($triggerId);
    }

    private function findChannel(string $tenantId, string $agentId, string $channelId): AiAgentChannel
    {
        $this->findAgent($tenantId, $agentId);

        return AiAgentChannel::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $agentId)
            ->findOrFail($channelId);
    }

    private function findSkill(string $tenantId, string $agentId, string $skillId): AiAgentSkill
    {
        $this->findAgent($tenantId, $agentId);

        return AiAgentSkill::query()
            ->where('tenant_id', $tenantId)
            ->where('agent_id', $agentId)
            ->findOrFail($skillId);
    }

    /**
     * @param  list<array<string, mixed>>  $catalog
     * @param  list<string>  $toolNames
     * @return list<array<string, string>>
     */
    private function mapSelectedToolsToLinks(array $catalog, array $toolNames): array
    {
        $catalogByName = collect($catalog)
            ->filter(static fn (array $tool): bool => is_string(data_get($tool, 'name')))
            ->keyBy(static fn (array $tool): string => (string) data_get($tool, 'name'));

        return array_values(array_map(
            static function (string $toolName) use ($catalogByName): array {
                /** @var array<string, mixed> $catalogItem */
                $catalogItem = $catalogByName->get($toolName, []);

                return [
                    'tool_id' => $toolName,
                    'tool_name' => $toolName,
                    'name' => $toolName,
                    'display_name' => (string) data_get($catalogItem, 'display_name', $toolName),
                    'description' => (string) data_get($catalogItem, 'description', ''),
                ];
            },
            $toolNames,
        ));
    }
}
