<?php

declare(strict_types=1);

namespace Domain\Ai\Actions;

use Domain\Ai\DTOs\AiAgentDTO;
use Domain\Ai\Models\AiAgent;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Casos de uso para agentes de IA.
 *
 * @category Actions
 */
final class AiAgentActions
{
    /**
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

    public function find(string $tenantId, string $id): AiAgent
    {
        return AiAgent::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }

    public function create(string $tenantId, AiAgentDTO $dto): AiAgent
    {
        return AiAgent::query()->create([
            'tenant_id' => $tenantId,
            ...$dto->toArray(),
        ]);
    }

    public function update(string $tenantId, string $id, AiAgentDTO $dto): AiAgent
    {
        $agent = $this->find($tenantId, $id);
        $agent->fill($dto->toArray());
        $agent->save();

        return $agent;
    }

    public function delete(string $tenantId, string $id): void
    {
        $agent = $this->find($tenantId, $id);
        $agent->delete();
    }

    public function toggleActive(string $tenantId, string $id): AiAgent
    {
        $agent = $this->find($tenantId, $id);
        $agent->is_active = ! $agent->is_active;
        $agent->save();

        return $agent;
    }
}
