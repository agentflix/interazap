<?php

declare(strict_types=1);

namespace Domain\Ai\Contracts;

/**
 * Contract for agent tool permission operations.
 *
 * Defines the interface for reading and writing tool permissions
 * scoped to a specific agent within a tenant.
 *
 * @category Contracts
 */
interface AiAgentToolPermissionServiceInterface
{
    /**
     * Returns the names of active tools linked to the agent.
     *
     * @param  string  $tenantId  UUID do tenant
     * @param  string  $agentId  UUID do agente
     * @return list<string> Nomes das tools ativas vinculadas
     */
    public function toolNamesForAgent(string $tenantId, string $agentId): array;

    /**
     * Checks if the agent can use the specified tool.
     *
     * @param  string  $tenantId  UUID do tenant
     * @param  string  $agentId  UUID do agente
     * @param  string  $toolName  Nome da tool a verificar
     */
    public function agentCanUseTool(string $tenantId, string $agentId, string $toolName): bool;

    /**
     * Synchronizes the agent's tool permissions.
     *
     * Replaces ALL previous permissions with the new ones.
     *
     * @param  string  $tenantId  UUID do tenant
     * @param  string  $agentId  UUID do agente
     * @param  list<string>  $toolNames  Nomes das tools a vincular
     */
    public function syncAgentTools(string $tenantId, string $agentId, array $toolNames): void;
}
