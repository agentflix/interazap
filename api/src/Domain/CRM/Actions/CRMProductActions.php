<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\DTOs\CRMProductDTO;
use Domain\CRM\Models\CRMProduct;
use Domain\Shared\Concerns\GuardsUniqueName;
use Domain\Shared\Support\ListFilterNormalizer;
use Domain\Shared\Support\SearchSanitizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Casos de uso para produtos/serviços do CRM.
 */
final class CRMProductActions
{
    use GuardsUniqueName;

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = CRMProduct::query()
            ->select([
                'id',
                'tenant_id',
                'name',
                'code',
                'description',
                'type',
                'price',
                'cost',
                'unit',
                'stock_quantity',
                'min_stock',
                'is_active',
                'is_featured',
                'track_stock',
                'stock',
                'created_at',
                'updated_at',
            ])
            ->where('tenant_id', $tenantId);

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            if ($search !== '') {
                $normalizedSearch = mb_strtolower($search);
                $statusSearch = match ($normalizedSearch) {
                    'active', 'ativo', 'ativos' => true,
                    'inactive', 'inativo', 'inativos' => false,
                    default => null,
                };

                $query->where(function ($q) use ($search, $statusSearch): void {
                    $q->where('name', 'ilike', SearchSanitizer::likeContains($search))
                        ->orWhere('code', 'ilike', SearchSanitizer::likeContains($search))
                        ->orWhere('description', 'ilike', SearchSanitizer::likeContains($search));

                    if (is_bool($statusSearch)) {
                        $q->orWhere('is_active', $statusSearch);
                    }
                });
            }
        }

        if (! empty($filters['type']) && in_array($filters['type'], ['product', 'service'], true)) {
            $query->where('type', $filters['type']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $isActive = ListFilterNormalizer::normalizeIsActive($filters['is_active']);
            $query->where('is_active', (bool) $isActive);
        }

        $allowedSortBy = ['name', 'code', 'price', 'created_at', 'updated_at', 'is_active', 'type'];
        $sortBy = ListFilterNormalizer::normalizeSortBy($filters['sort_by'] ?? null, $allowedSortBy, 'name');
        $sortDir = ListFilterNormalizer::normalizeSortDirection($filters['sort_dir'] ?? null);
        $perPage = ListFilterNormalizer::normalizePerPage($filters['per_page'] ?? null);

        return $query
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);
    }

    /**
     * @return Collection<int, CRMProduct>
     */
    public function listAll(string $tenantId, ?string $type = null): Collection
    {
        $query = CRMProduct::query()
            ->select([
                'id',
                'tenant_id',
                'name',
                'code',
                'price',
                'cost',
                'unit',
                'type',
                'description',
                'is_active',
                'is_featured',
                'stock_quantity',
                'min_stock',
                'track_stock',
                'stock',
            ])
            ->where('tenant_id', $tenantId)
            ->where('is_active', true);

        if ($type !== null && in_array($type, ['product', 'service'], true)) {
            $query->where('type', $type);
        }

        return $query->orderBy('name')->get();
    }

    public function create(string $tenantId, CRMProductDTO $dto): CRMProduct
    {
        $this->guardUniqueName(CRMProduct::class, $tenantId, $dto->name, 'Produto já cadastrado para este tenant.');

        return DB::transaction(function () use ($tenantId, $dto): CRMProduct {
            return CRMProduct::query()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $tenantId,
                ...$dto->toArray(),
            ]);
        });
    }

    public function update(string $tenantId, string $id, CRMProductDTO $dto): CRMProduct
    {
        $product = $this->find($tenantId, $id);
        if ($product->name !== $dto->name) {
            $this->guardUniqueName(CRMProduct::class, $tenantId, $dto->name, 'Produto já cadastrado para este tenant.');
        }

        $product->fill($dto->toArray());
        $product->save();

        return $product;
    }

    public function delete(string $tenantId, string $id): void
    {
        $product = $this->find($tenantId, $id);
        $product->delete();
    }

    public function find(string $tenantId, string $id): CRMProduct
    {
        return CRMProduct::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }
}
