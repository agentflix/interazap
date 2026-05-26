<?php

declare(strict_types=1);

namespace Domain\Ai\Services;

use Domain\Ai\Contracts\AiAgentToolPermissionServiceInterface;
use Domain\Ai\Models\AiAutopilotTool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Serviço central de permissões de tools por agente.
 *
 * Responsável por ler, verificar e sincronizar as tools vinculadas a um agente,
 * garantindo isolamento multi-tenant em todas as operações.
 *
 * Implementa os contratos de leitura e escrita de permissões de tools,
 * operando exclusivamente sobre os models AiAgent e AiAutopilotTool
 * com filtro obrigatório por tenant_id.
 */
final class AiAgentToolPermissionService implements AiAgentToolPermissionServiceInterface
{
    /**
     * Retorna os nomes das tools ativas vinculadas ao agente.
     *
     * Consulta a tabela pivot ai_agent_tools filtrando por tenant e agent,
     * juntando com ai_autopilot_tools para garantir que apenas tools ativas
     * sejam retornadas.
     *
     * @param  string  $tenantId  UUID do tenant
     * @param  string  $agentId  UUID do agente
     * @return list<string> Nomes das tools ativas vinculadas
     */
    public function toolNamesForAgent(string $tenantId, string $agentId): array
    {
        /** @var list<string> $names */
        $names = DB::table('ai_agent_tools')
            ->join('ai_autopilot_tools', 'ai_agent_tools.tool_id', '=', 'ai_autopilot_tools.id')
            ->where('ai_agent_tools.tenant_id', $tenantId)
            ->where('ai_agent_tools.agent_id', $agentId)
            ->where('ai_autopilot_tools.is_active', true)
            ->pluck('ai_autopilot_tools.name')
            ->toArray();

        return $names;
    }

    /**
     * Verifica se o agente pode utilizar a tool informada.
     *
     * Retorna true somente quando o agente possui a tool vinculada
     * E a tool está com status ativo (is_active = true).
     *
     * @param  string  $tenantId  UUID do tenant
     * @param  string  $agentId  UUID do agente
     * @param  string  $toolName  Nome da tool a verificar
     */
    public function agentCanUseTool(string $tenantId, string $agentId, string $toolName): bool
    {
        return DB::table('ai_agent_tools')
            ->join('ai_autopilot_tools', 'ai_agent_tools.tool_id', '=', 'ai_autopilot_tools.id')
            ->where('ai_agent_tools.tenant_id', $tenantId)
            ->where('ai_agent_tools.agent_id', $agentId)
            ->where('ai_autopilot_tools.name', $toolName)
            ->where('ai_autopilot_tools.is_active', true)
            ->exists();
    }

    /**
     * Sincroniza as permissões de tools do agente.
     *
     * Substitui TODAS as permissões anteriores pelas novas.
     * Resolve os nomes informados por (tenant_id, name) em ai_autopilot_tools,
     * ignorando nomes inexistentes ou inativos.
     *
     * @param  string  $tenantId  UUID do tenant
     * @param  string  $agentId  UUID do agente
     * @param  list<string>  $toolNames  Nomes das tools a vincular
     */
    public function syncAgentTools(string $tenantId, string $agentId, array $toolNames): void
    {
        DB::transaction(function () use ($tenantId, $agentId, $toolNames): void {
            // Resolve tool IDs válidos: existentes, do tenant correto e ativos
            $validToolIds = AiAutopilotTool::forTenant($tenantId)
                ->whereIn('name', $toolNames)
                ->where('is_active', true)
                ->pluck('id')
                ->toArray();

            // Remove todas as permissões atuais do agente
            DB::table('ai_agent_tools')
                ->where('tenant_id', $tenantId)
                ->where('agent_id', $agentId)
                ->delete();

            // Insere as novas permissões (uma por tool válida)
            if (empty($validToolIds)) {
                return;
            }

            $now = now();

            /** @var Collection<int, string> $toolIdCollection */
            $toolIdCollection = collect($validToolIds);

            $rows = $toolIdCollection->map(fn (string $toolId): array => [
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                'agent_id' => $agentId,
                'tool_id' => $toolId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->toArray();

            DB::table('ai_agent_tools')->insert($rows);
        });
    }
}
