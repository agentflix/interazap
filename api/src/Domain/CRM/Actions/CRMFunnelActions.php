<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\Models\CRMNegotiationFunnel;
use Domain\CRM\Models\CRMNegotiationFunnelStep;
use Domain\Shared\Concerns\GuardsUniqueName;
use Domain\Shared\Support\SearchSanitizer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Casos de uso para funis e etapas.
 */
final class CRMFunnelActions
{
    use GuardsUniqueName;

    /**
     * Lista funis com filtros de busca e status, com paginação.
     *
     * @param  array<string, mixed>  $filters
     */
    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = CRMNegotiationFunnel::query()
            ->select(['id', 'tenant_id', 'name', 'description', 'is_active', 'created_at', 'updated_at'])
            ->where('tenant_id', $tenantId)
            ->with('steps:id,tenant_id,crm_negotiation_funnel_id,name,color,order,is_active');

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            if ($search !== '') {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'ilike', SearchSanitizer::likeContains($search))
                        ->orWhere('description', 'ilike', SearchSanitizer::likeContains($search));
                });
            }
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query
            ->orderBy('name')
            ->paginate();
    }

    /** Retorna todos os funis ativos do tenant sem paginação, incluindo etapas. */
    public function all(string $tenantId): Collection
    {
        return CRMNegotiationFunnel::query()
            ->select(['id', 'tenant_id', 'name', 'description', 'is_active'])
            ->where('tenant_id', $tenantId)
            ->with('steps:id,tenant_id,crm_negotiation_funnel_id,name,color,order,is_active')
            ->orderBy('name')
            ->get();
    }

    /**
     * Cria um funil garantindo unicidade de nome no tenant.
     *
     * @throws \Illuminate\Validation\ValidationException Quando o nome já existe no tenant
     */
    public function create(string $tenantId, string $name, ?string $description = null, bool $isActive = true): CRMNegotiationFunnel
    {
        $this->guardUniqueName(CRMNegotiationFunnel::class, $tenantId, $name, 'Funil já cadastrado para este tenant.');

        return CRMNegotiationFunnel::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'name' => $name,
            'description' => $description,
            'is_active' => $isActive,
        ]);
    }

    /**
     * Atualiza os dados de um funil, verificando unicidade de nome se alterado.
     *
     * @param  array<string, mixed>  $data  Campos a atualizar (name, description, is_active)
     * @return CRMNegotiationFunnel Funil atualizado com etapas carregadas
     *
     * @throws \Illuminate\Validation\ValidationException Quando o novo nome já existe no tenant
     */
    public function update(string $tenantId, string $id, array $data): CRMNegotiationFunnel
    {
        $funnel = $this->find($tenantId, $id);
        if (isset($data['name']) && $data['name'] !== $funnel->name) {
            $this->guardUniqueName(CRMNegotiationFunnel::class, $tenantId, $data['name'], 'Funil já cadastrado para este tenant.');
        }

        $funnel->fill([
            'name' => $data['name'] ?? $funnel->name,
            'description' => $data['description'] ?? $funnel->description,
            'is_active' => $data['is_active'] ?? $funnel->is_active,
        ]);
        $funnel->save();

        return $funnel->load('steps');
    }

    /** Remove um funil pelo ID. */
    public function delete(string $tenantId, string $id): void
    {
        $funnel = $this->find($tenantId, $id);
        $funnel->delete();
    }

    /**
     * Adiciona uma etapa ao funil.
     *
     * @param  int  $order  Posição da etapa no funil
     */
    public function addStep(
        string $tenantId,
        string $funnelId,
        string $name,
        int $order,
        ?string $color = null,
        bool $isActive = true
    ): CRMNegotiationFunnelStep {
        $this->find($tenantId, $funnelId);

        return CRMNegotiationFunnelStep::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'crm_negotiation_funnel_id' => $funnelId,
            'name' => $name,
            'color' => $color,
            'is_active' => $isActive,
            'order' => $order,
        ]);
    }

    /**
     * Lista as etapas de um funil ordenadas por posição.
     *
     * @return \Illuminate\Support\Collection<int, CRMNegotiationFunnelStep>
     */
    public function listSteps(string $tenantId, string $funnelId): Collection
    {
        $this->find($tenantId, $funnelId);

        return CRMNegotiationFunnelStep::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_negotiation_funnel_id', $funnelId)
            ->orderBy('order')
            ->get();
    }

    /**
     * Atualiza os dados de uma etapa do funil.
     *
     * @param  array<string, mixed>  $payload  Campos a atualizar (name, color, is_active, order)
     * @return CRMNegotiationFunnelStep Etapa atualizada
     */
    public function updateStep(string $tenantId, string $funnelId, string $stepId, array $payload): CRMNegotiationFunnelStep
    {
        $this->find($tenantId, $funnelId);

        $step = CRMNegotiationFunnelStep::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_negotiation_funnel_id', $funnelId)
            ->findOrFail($stepId);

        $updates = [
            'name' => $payload['name'] ?? $step->name,
        ];

        if (array_key_exists('color', $payload)) {
            $updates['color'] = $payload['color'];
        }

        if (array_key_exists('is_active', $payload)) {
            $updates['is_active'] = (bool) $payload['is_active'];
        }

        if (array_key_exists('order', $payload)) {
            $updates['order'] = (int) $payload['order'];
        }

        $step->fill($updates);
        $step->save();

        return $step;
    }

    /** Remove uma etapa de um funil pelo ID. */
    public function deleteStep(string $tenantId, string $funnelId, string $stepId): void
    {
        $this->find($tenantId, $funnelId);

        CRMNegotiationFunnelStep::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_negotiation_funnel_id', $funnelId)
            ->where('id', $stepId)
            ->delete();
    }

    /**
     * Reordena as etapas de um funil conforme a lista fornecida.
     *
     * Usa offset de +1000 para evitar conflitos de chave única durante a reordenação atômica.
     *
     * @param  array<int, array<string, int|string>>  $steps  Lista de objetos com 'id' e 'order'
     */
    public function reorder(string $tenantId, string $funnelId, array $steps): void
    {
        $this->find($tenantId, $funnelId);
        $stepIds = collect($steps)->pluck('id')->all();

        DB::transaction(function () use ($tenantId, $funnelId, $steps, $stepIds): void {
            CRMNegotiationFunnelStep::query()
                ->where('tenant_id', $tenantId)
                ->where('crm_negotiation_funnel_id', $funnelId)
                ->whereIn('id', $stepIds)
                ->update(['order' => DB::raw('"order" + 1000')]);

            foreach ($steps as $step) {
                CRMNegotiationFunnelStep::query()
                    ->where('tenant_id', $tenantId)
                    ->where('id', $step['id'])
                    ->update(['order' => (int) $step['order']]);
            }
        });
    }

    /** Retorna um funil pelo ID com suas etapas, lançando 404 se não pertencer ao tenant. */
    public function find(string $tenantId, string $id): CRMNegotiationFunnel
    {
        return CRMNegotiationFunnel::query()
            ->where('tenant_id', $tenantId)
            ->with('steps')
            ->findOrFail($id);
    }
}
