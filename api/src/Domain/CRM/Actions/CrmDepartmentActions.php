<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\DTOs\CRMDepartmentDTO;
use Domain\CRM\Models\CRMDepartment;
use Domain\Shared\Concerns\GuardsUniqueName;
use Domain\Shared\Support\ListFilterNormalizer;
use Domain\Shared\Support\SearchSanitizer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Casos de uso para departamentos.
 */
final class CRMDepartmentActions
{
    use GuardsUniqueName;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = CRMDepartment::query()
            ->select(['id', 'tenant_id', 'name', 'description', 'is_active', 'created_at', 'updated_at'])
            ->where('tenant_id', $tenantId);

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
            $isActive = ListFilterNormalizer::normalizeIsActive($filters['is_active']);
            $query->where('is_active', (bool) $isActive);
        }

        $allowedSortBy = ['name', 'created_at', 'updated_at', 'is_active'];
        $sortBy = ListFilterNormalizer::normalizeSortBy($filters['sort_by'] ?? null, $allowedSortBy, 'name');
        $sortDir = ListFilterNormalizer::normalizeSortDirection($filters['sort_dir'] ?? null);
        $perPage = ListFilterNormalizer::normalizePerPage($filters['per_page'] ?? null);

        return $query
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);
    }

    /**
     * @return Collection<int, CRMDepartment>
     */
    public function all(string $tenantId): Collection
    {
        return CRMDepartment::query()
            ->select(['id', 'tenant_id', 'name', 'description', 'is_active'])
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();
    }

    public function create(string $tenantId, CRMDepartmentDTO $dto): CRMDepartment
    {
        $this->guardUniqueName(CRMDepartment::class, $tenantId, $dto->name, 'Departamento já cadastrado para este tenant.');

        return CRMDepartment::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            ...$dto->toArray(),
        ]);
    }

    public function update(string $tenantId, string $id, CRMDepartmentDTO $dto): CRMDepartment
    {
        $department = $this->find($tenantId, $id);
        if ($department->name !== $dto->name) {
            $this->guardUniqueName(CRMDepartment::class, $tenantId, $dto->name, 'Departamento já cadastrado para este tenant.');
        }

        $department->fill($dto->toArray());
        $department->save();

        return $department;
    }

    public function delete(string $tenantId, string $id): void
    {
        $department = $this->find($tenantId, $id);
        $department->delete();
    }

    public function toggleActive(string $tenantId, string $id): CRMDepartment
    {
        $department = $this->find($tenantId, $id);
        $department->is_active = ! $department->is_active;
        $department->save();

        return $department;
    }

    public function find(string $tenantId, string $id): CRMDepartment
    {
        return CRMDepartment::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }
}
