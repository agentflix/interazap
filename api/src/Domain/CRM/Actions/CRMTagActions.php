<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\DTOs\CRMTagDTO;
use Domain\CRM\Models\CRMCompany;
use Domain\CRM\Models\CRMContact;
use Domain\CRM\Models\CRMNegotiation;
use Domain\CRM\Models\CRMTag;
use Domain\Shared\Concerns\GuardsUniqueName;
use Domain\Shared\Support\ListFilterNormalizer;
use Domain\Shared\Support\SearchSanitizer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Casos de uso para tags CRM.
 */
final class CRMTagActions
{
    use GuardsUniqueName;

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, CRMTag>
     */
    public function all(string $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return CRMTag::query()
            ->select(['id', 'tenant_id', 'name', 'color', 'category', 'is_active', 'created_at', 'updated_at'])
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('name', 'asc')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = CRMTag::query()
            ->select(['id', 'tenant_id', 'name', 'color', 'category', 'is_active', 'created_at', 'updated_at'])
            ->where('tenant_id', $tenantId);

        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            if ($search !== '') {
                $query->where(function ($q) use ($search): void {
                    $q->where('name', 'ilike', SearchSanitizer::likeContains($search))
                        ->orWhere('category', 'ilike', SearchSanitizer::likeContains($search));
                });
            }
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $isActive = ListFilterNormalizer::normalizeIsActive($filters['is_active']);
            $query->where('is_active', (bool) $isActive);
        }

        $allowedSortBy = ['name', 'created_at', 'updated_at', 'is_active', 'category'];
        $sortBy = ListFilterNormalizer::normalizeSortBy($filters['sort_by'] ?? null, $allowedSortBy, 'name');
        $sortDir = ListFilterNormalizer::normalizeSortDirection($filters['sort_dir'] ?? null);
        $perPage = ListFilterNormalizer::normalizePerPage($filters['per_page'] ?? null);

        return $query
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);
    }

    public function create(string $tenantId, CRMTagDTO $dto): CRMTag
    {
        $this->guardUniqueName(CRMTag::class, $tenantId, $dto->name, 'Tag já cadastrada para este tenant.');

        return CRMTag::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            ...$dto->toArray(),
        ]);
    }

    public function find(string $tenantId, string $id): CRMTag
    {
        return CRMTag::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->firstOrFail();
    }

    public function delete(string $tenantId, string $id): void
    {
        $tag = $this->find($tenantId, $id);
        $tag->delete();
    }

    public function update(string $tenantId, string $id, CRMTagDTO $dto): CRMTag
    {
        $tag = $this->find($tenantId, $id);

        if ($tag->name !== $dto->name) {
            $this->guardUniqueName(
                CRMTag::class,
                $tenantId,
                $dto->name,
                'Tag já cadastrada para este tenant.',
                $id,
            );
        }

        $tag->update($dto->toArray());

        return $tag;
    }

    public function attachToContact(string $tenantId, string $contactId, string $tagId): void
    {
        $contact = CRMContact::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($contactId);

        $contact->tags()->syncWithoutDetaching([
            $tagId => ['tenant_id' => $tenantId, 'id' => (string) Str::orderedUuid()],
        ]);
    }

    public function detachFromContact(string $tenantId, string $contactId, string $tagId): void
    {
        $contact = CRMContact::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($contactId);

        $contact->tags()->detach($tagId);
    }

    public function attachToCompany(string $tenantId, string $companyId, string $tagId): void
    {
        $company = CRMCompany::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($companyId);

        $company->tags()->syncWithoutDetaching([
            $tagId => ['tenant_id' => $tenantId, 'id' => (string) Str::orderedUuid()],
        ]);
    }

    public function detachFromCompany(string $tenantId, string $companyId, string $tagId): void
    {
        $company = CRMCompany::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($companyId);

        $company->tags()->detach($tagId);
    }

    public function attachToNegotiation(string $tenantId, string $negotiationId, string $tagId): void
    {
        $negotiation = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($negotiationId);

        $negotiation->tags()->syncWithoutDetaching([
            $tagId => ['tenant_id' => $tenantId, 'id' => (string) Str::orderedUuid()],
        ]);
    }

    public function detachFromNegotiation(string $tenantId, string $negotiationId, string $tagId): void
    {
        $negotiation = CRMNegotiation::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($negotiationId);

        $negotiation->tags()->detach($tagId);
    }
}
