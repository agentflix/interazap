<?php

declare(strict_types=1);

namespace Domain\Ai\Actions;

use Domain\Ai\DTOs\AiAgentDTO;
use Domain\Ai\Models\AiAgent;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Casos de uso CRUD para agentes de IA.
 *
 * Contexto: ponto central de acesso ao model AiAgent, garantindo
 * isolamento multi-tenant em todas as operações. Utilizado por
 * controllers HTTP e outras Actions que dependem de agentes.
 */
final class AiAgentActions
{
    /**
     * Lista agentes do tenant com paginação e busca opcional por nome.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string|null  $search  Termo de busca parcial no nome do agente.
     * @return LengthAwarePaginator<int, AiAgent>
     */
    public function list(string $tenantId, ?string $search = null): LengthAwarePaginator
    {
        return AiAgent::query()
            ->where('tenant_id', $tenantId)
            ->when(
                $search !== null && $search !== '',
                fn ($query) => $query->where('name', 'like', '%'.$search.'%')
            )
            ->latest()
            ->paginate();
    }

    /**
     * Localiza um agente pelo ID, garantindo pertencimento ao tenant.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $id  UUID do agente.
     * @return AiAgent Agente encontrado.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function find(string $tenantId, string $id): AiAgent
    {
        return AiAgent::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }

    /**
     * Cria um novo agente para o tenant.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  AiAgentDTO  $dto  Dados do agente a criar.
     * @return AiAgent Agente criado.
     */
    public function create(string $tenantId, AiAgentDTO $dto): AiAgent
    {
        return AiAgent::query()->create([
            'tenant_id' => $tenantId,
            ...$dto->toArray(),
        ]);
    }

    /**
     * Atualiza os dados de um agente existente.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $id  UUID do agente.
     * @param  AiAgentDTO  $dto  Novos dados do agente.
     * @return AiAgent Agente atualizado.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function update(string $tenantId, string $id, AiAgentDTO $dto): AiAgent
    {
        $agent = $this->find($tenantId, $id);
        $agent->fill($dto->toArray());
        $agent->save();

        return $agent;
    }

    /**
     * Remove um agente do tenant.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $id  UUID do agente.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function delete(string $tenantId, string $id): void
    {
        $agent = $this->find($tenantId, $id);
        $agent->delete();
    }

    /**
     * Alterna o status ativo/inativo de um agente.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $id  UUID do agente.
     * @return AiAgent Agente com o novo status aplicado.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException
     */
    public function toggleActive(string $tenantId, string $id): AiAgent
    {
        $agent = $this->find($tenantId, $id);
        $agent->is_active = ! $agent->is_active;
        $agent->save();

        return $agent;
    }
}
