<?php

declare(strict_types=1);

namespace Domain\Ai\Actions;

use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAgentChannel;
use Domain\Ai\Models\AiAgentDelegation;
use Domain\Ai\Models\AiAgentFile;
use Domain\Ai\Models\AiAgentSkill;
use Domain\Ai\Models\AiAgentTrigger;
use Domain\Ai\Services\AiAgentToolPermissionService;
use Domain\Ai\Services\ToolDispatcherService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Gerencia sub-recursos de agentes de IA sem alterar contratos de controllers.
 *
 * Contexto: centraliza operações sobre arquivos, tools, triggers, canais,
 * skills e delegações de um agente, garantindo isolamento multi-tenant e
 * validação de existência do agente pai em cada operação.
 */
final class AiAgentSubresourceActions
{
    /**
     * Injeta dependências de acesso a agentes, dispatch de tools e controle de permissões.
     *
     * @param  AiAgentActions  $agentActions  Serviço de acesso ao agente pai.
     * @param  ToolDispatcherService  $toolDispatcher  Serviço de execução de tools.
     * @param  AiAgentToolPermissionService  $toolPermissions  Serviço de permissões de tools por agente.
     */
    public function __construct(
        private readonly AiAgentActions $agentActions,
        private readonly ToolDispatcherService $toolDispatcher,
        private readonly AiAgentToolPermissionService $toolPermissions,
    ) {}

    /**
     * Lista arquivos do agente ordenados por slug.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
     * @return Collection<int, AiAgentFile>
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Se o agente não existir.
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
     * Localiza um arquivo do agente pelo slug dentro do tenant.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
     * @param  string  $slug  Identificador textual do arquivo.
     * @return AiAgentFile Arquivo encontrado.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Se o arquivo não existir.
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
     * Cria ou atualiza um arquivo do agente identificado por slug.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
     * @param  string  $slug  Identificador textual do arquivo.
     * @param  string|null  $content  Conteúdo do arquivo.
     * @param  string|null  $userId  UUID do usuário que realizou a alteração.
     * @return AiAgentFile Arquivo criado ou atualizado.
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
     * Lista as tools do agent lendo da tabela pivot ai_agent_tools.
     *
     * @return list<array<string, string>>
     */
    public function listTools(string $tenantId, string $agentId): array
    {
        $this->findAgent($tenantId, $agentId);

        $selectedToolNames = $this->toolPermissions->toolNamesForAgent($tenantId, $agentId);

        return $this->mapSelectedToolsToLinks(
            $this->toolDispatcher->getCatalog($tenantId),
            $selectedToolNames,
        );
    }

    /**
     * Sincroniza as tools do agent na tabela pivot ai_agent_tools.
     *
     * Valida os nomes solicitados contra o catálogo de tools ativas do tenant,
     * ignorando tools inexistentes ou inativas. Remove legacy metadata.tool_names
     * se existir, preservando demais chaves.
     *
     * @param  array<string, mixed>  $validated
     * @return list<array<string, string>>
     */
    public function updateTools(string $tenantId, string $agentId, array $validated): array
    {
        $agent = $this->findAgent($tenantId, $agentId);

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

        $this->toolPermissions->syncAgentTools($tenantId, $agentId, $toolNames);

        // Limpa legacy metadata.tool_names se existir, preservando demais chaves
        if (is_array($agent->metadata) && array_key_exists('tool_names', $agent->metadata)) {
            $metadata = $agent->metadata;
            unset($metadata['tool_names']);
            $agent->metadata = $metadata;
            $agent->save();
        }

        // Invalida cache de tool definitions do InternalAiController
        Cache::forget(sprintf('internal:ai:tools:%s', $agentId));

        return $this->mapSelectedToolsToLinks($catalog, $toolNames);
    }

    /**
     * Lista triggers do agente em ordem decrescente de criação.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
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
     * Cria um novo trigger para o agente.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
     * @param  array<string, mixed>  $attributes  Atributos do trigger.
     * @return AiAgentTrigger Trigger criado.
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
     * Atualiza atributos de um trigger existente.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
     * @param  string  $triggerId  UUID do trigger.
     * @param  array<string, mixed>  $attributes  Novos atributos.
     * @return AiAgentTrigger Trigger atualizado.
     */
    public function updateTrigger(string $tenantId, string $agentId, string $triggerId, array $attributes): AiAgentTrigger
    {
        $trigger = $this->findTrigger($tenantId, $agentId, $triggerId);
        $trigger->fill($attributes);
        $trigger->save();

        return $trigger;
    }

    /**
     * Remove um trigger do agente.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
     * @param  string  $triggerId  UUID do trigger.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Se o trigger não existir.
     */
    public function deleteTrigger(string $tenantId, string $agentId, string $triggerId): void
    {
        $this->findTrigger($tenantId, $agentId, $triggerId)->delete();
    }

    /**
     * Lista canais do agente ordenados por canal e referência externa.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
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
     * Cria um novo canal para o agente.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
     * @param  array<string, mixed>  $attributes  Atributos do canal.
     * @return AiAgentChannel Canal criado.
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
     * Atualiza atributos de um canal existente.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
     * @param  string  $channelId  UUID do canal.
     * @param  array<string, mixed>  $attributes  Novos atributos.
     * @return AiAgentChannel Canal atualizado.
     */
    public function updateChannel(string $tenantId, string $agentId, string $channelId, array $attributes): AiAgentChannel
    {
        $channel = $this->findChannel($tenantId, $agentId, $channelId);
        $channel->fill($attributes);
        $channel->save();

        return $channel;
    }

    /**
     * Remove um canal do agente.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
     * @param  string  $channelId  UUID do canal.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Se o canal não existir.
     */
    public function deleteChannel(string $tenantId, string $agentId, string $channelId): void
    {
        $this->findChannel($tenantId, $agentId, $channelId)->delete();
    }

    /**
     * Lista skills do agente ordenadas por nome.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
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
     * Cria uma nova skill para o agente.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
     * @param  array<string, mixed>  $attributes  Atributos da skill.
     * @return AiAgentSkill Skill criada.
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
     * Atualiza atributos de uma skill existente.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
     * @param  string  $skillId  UUID da skill.
     * @param  array<string, mixed>  $attributes  Novos atributos.
     * @return AiAgentSkill Skill atualizada.
     */
    public function updateSkill(string $tenantId, string $agentId, string $skillId, array $attributes): AiAgentSkill
    {
        $skill = $this->findSkill($tenantId, $agentId, $skillId);
        $skill->fill($attributes);
        $skill->save();

        return $skill;
    }

    /**
     * Remove uma skill do agente.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
     * @param  string  $skillId  UUID da skill.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Se a skill não existir.
     */
    public function deleteSkill(string $tenantId, string $agentId, string $skillId): void
    {
        $this->findSkill($tenantId, $agentId, $skillId)->delete();
    }

    /**
     * Lista as delegações onde este agente é o agente de origem.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente de origem.
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
     * Cria ou atualiza uma regra de delegação entre dois agentes.
     *
     * Valida que ambos (origem e destino) pertencem ao tenant antes de persistir.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente de origem.
     * @param  array<string, mixed>  $attributes  Atributos da delegação (target_agent_id, max_depth, etc.).
     * @return AiAgentDelegation Delegação criada ou atualizada com agente-destino carregado.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Se algum agente não existir.
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
     * Remove uma delegação do agente de origem.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente de origem.
     * @param  string  $delegationId  UUID da delegação.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Se a delegação não existir.
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
     * Retorna as configurações de voz do agente.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
     * @return array<string, mixed> Configurações de STT e TTS do agente.
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
     * Atualiza as configurações de voz do agente.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $agentId  UUID do agente.
     * @param  array<string, mixed>  $attributes  Novos atributos de voz.
     * @return AiAgent Agente com configurações atualizadas.
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
     * Mapeia nomes de tools selecionadas para links com metadados do catálogo.
     *
     * @param  list<array<string, mixed>>  $catalog
     * @param  list<string>  $toolNames
     * @return list<array<string, string>>
     */
    public function mapSelectedToolsToLinks(array $catalog, array $toolNames): array
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
