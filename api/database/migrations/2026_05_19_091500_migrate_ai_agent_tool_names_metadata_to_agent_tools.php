<?php

declare(strict_types=1);

use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAutopilotTool;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Migra os nomes de tools salvos em ai_agents.metadata->tool_names
 * para a tabela pivot ai_agent_tools, removendo apenas a chave tool_names.
 */
final class MigrateAiAgentToolNamesMetadataToAgentTools extends Migration
{
    /**
     * Executa a migração: backfill de ai_agent_tools a partir de metadata.tool_names.
     */
    public function up(): void
    {
        AiAgent::query()
            ->whereRaw("metadata->>'tool_names' IS NOT NULL")
            ->chunkById(100, function ($agents): void {
                foreach ($agents as $agent) {
                    $this->migrateAgentTools($agent);
                }
            });
    }

    /**
     * Migra as tools de um único agent do metadata para a tabela pivot.
     *
     * @param  AiAgent  $agent  Agent a ser migrado.
     */
    private function migrateAgentTools(AiAgent $agent): void
    {
        /** @var array<string, mixed> $metadata */
        $metadata = $agent->metadata ?? [];
        $toolNames = $metadata['tool_names'] ?? null;

        if (! is_array($toolNames) || $toolNames === []) {
            return;
        }

        foreach ($toolNames as $toolName) {
            if (! is_string($toolName)) {
                continue;
            }

            // L3: filtra apenas tools ativas
            $tool = AiAutopilotTool::query()
                ->where('tenant_id', $agent->tenant_id)
                ->where('name', $toolName)
                ->where('is_active', true)
                ->first();

            if (! $tool) {
                Log::warning(
                    "[MigrateAiAgentToolNamesMetadata] Tool '{$toolName}' not found or inactive for tenant {$agent->tenant_id}, agent {$agent->id}. Skipping.",
                );

                continue;
            }

            // Insert or ignore — a unique constraint (agent_id, tool_id) previne duplicatas
            DB::table('ai_agent_tools')->insertOrIgnore([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $agent->tenant_id,
                'agent_id' => $agent->id,
                'tool_id' => $tool->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Remove apenas a chave tool_names, preservando demais chaves do metadata
        unset($metadata['tool_names']);

        $agent->updateQuietly(['metadata' => $metadata === [] ? null : $metadata]);
    }

    /**
     * Reverte a migração de forma segura.
     *
     * O rollback restaura metadata.tool_names a partir dos vínculos atuais em
     * ai_agent_tools (resolvendo o nome da tool) e remove os registros pivot
     * criados. Se não houver vínculo, nada é feito para o agent.
     */
    public function down(): void
    {
        // Não há como saber quais tool_names eram strings originais vs resolvidas.
        // Estratégia segura: reconstruir tool_names a partir dos pivots existentes
        // e depois deletar os pivots.
        $pivotRows = DB::table('ai_agent_tools')
            ->join('ai_autopilot_tools', 'ai_agent_tools.tool_id', '=', 'ai_autopilot_tools.id')
            ->select('ai_agent_tools.agent_id', 'ai_agent_tools.tenant_id', 'ai_autopilot_tools.name as tool_name')
            ->get();

        $grouped = [];
        foreach ($pivotRows as $row) {
            $grouped[$row->agent_id] ??= [];
            $grouped[$row->agent_id][] = $row->tool_name;
        }

        foreach ($grouped as $agentId => $toolNames) {
            $agent = AiAgent::query()->find($agentId);

            if (! $agent) {
                continue;
            }

            /** @var array<string, mixed> $metadata */
            $metadata = $agent->metadata ?? [];
            $metadata['tool_names'] = $toolNames;

            $agent->updateQuietly(['metadata' => $metadata]);
        }

        // Remove os pivots criados pela migration up
        // Como não temos como distinguir quais pivots foram criados por esta migration,
        // removemos todos que correspondem a agents que tinham tool_names no metadata
        // antes da up. Para segurança, removemos apenas pivots de agents cujo metadata
        // agora contém tool_names (recém-restaurado).
        $agentIds = array_keys($grouped);

        if ($agentIds !== []) {
            DB::table('ai_agent_tools')->whereIn('agent_id', $agentIds)->delete();
        }
    }
}
