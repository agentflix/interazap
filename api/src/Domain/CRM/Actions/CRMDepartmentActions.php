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
     * Lista departamentos do tenant com filtros de busca e status, com paginação.
     *
     * @param  array<string, mixed>  $filters  Filtros disponíveis: search, is_active, sort_by, sort_dir, per_page
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
     * Retorna todos os departamentos ativos do tenant sem paginação.
     *
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

    /**
     * Cria um departamento garantindo unicidade de nome no tenant.
     *
     * @throws \Illuminate\Validation\ValidationException Quando o nome já existe no tenant
     */
    public function create(string $tenantId, CRMDepartmentDTO $dto): CRMDepartment
    {
        $this->guardUniqueName(CRMDepartment::class, $tenantId, $dto->name, 'Departamento já cadastrado para este tenant.');

        return CRMDepartment::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            ...$dto->toArray(),
        ]);
    }

    /** Atualiza dados do departamento, verificando unicidade de nome se alterado. */
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

    /** Remove um departamento pelo ID. */
    public function delete(string $tenantId, string $id): void
    {
        $department = $this->find($tenantId, $id);
        $department->delete();
    }

    /** Alterna o status ativo/inativo do departamento. */
    public function toggleActive(string $tenantId, string $id): CRMDepartment
    {
        $department = $this->find($tenantId, $id);
        $department->is_active = ! $department->is_active;
        $department->save();

        return $department;
    }

    /** Retorna um departamento pelo ID, lançando 404 se não pertencer ao tenant. */
    public function find(string $tenantId, string $id): CRMDepartment
    {
        return CRMDepartment::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }
}
