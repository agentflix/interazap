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

    public function all(string $tenantId): Collection
    {
        return CRMNegotiationFunnel::query()
            ->select(['id', 'tenant_id', 'name', 'description', 'is_active'])
            ->where('tenant_id', $tenantId)
            ->with('steps:id,tenant_id,crm_negotiation_funnel_id,name,color,order,is_active')
            ->orderBy('name')
            ->get();
    }

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
     * @param  array<string, mixed>  $data
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

    public function delete(string $tenantId, string $id): void
    {
        $funnel = $this->find($tenantId, $id);
        $funnel->delete();
    }

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
     * @param  array<string, mixed>  $payload
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
     * @param  array<int, array<string, int|string>>  $steps
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

    public function find(string $tenantId, string $id): CRMNegotiationFunnel
    {
        return CRMNegotiationFunnel::query()
            ->where('tenant_id', $tenantId)
            ->with('steps')
            ->findOrFail($id);
    }
}
