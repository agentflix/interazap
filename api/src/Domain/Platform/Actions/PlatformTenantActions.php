<?php

declare(strict_types=1);

namespace Domain\Platform\Actions;

use Domain\Ai\Models\AiPromptSegment;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\DTOs\PlatformTenantDTO;
use Domain\Platform\Models\PlatformTenant;
use Domain\Platform\Services\PlatformTenantBootstrapCatalogService;
use Domain\Shared\Support\SearchSanitizer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Acoes para gerenciamento de tenants da plataforma.
 */
final class PlatformTenantActions
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = $this->buildFilteredQuery($filters);

        $sortBy = $this->sanitizeSortBy($filters['sort_by'] ?? 'name');
        $sortDir = $this->sanitizeSortDirection($filters['sort_dir'] ?? 'asc');
        $perPage = $this->sanitizePerPage((int) ($filters['per_page'] ?? 15));

        return $query->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PlatformTenant>
     */
    public function queryForExport(array $filters = []): Builder
    {
        $query = $this->buildFilteredQuery($filters);
        $sortBy = $this->sanitizeSortBy($filters['sort_by'] ?? 'name');
        $sortDir = $this->sanitizeSortDirection($filters['sort_dir'] ?? 'asc');

        return $query->orderBy($sortBy, $sortDir);
    }

    public function find(string $id, bool $withTrashed = false): PlatformTenant
    {
        $query = PlatformTenant::query();

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->findOrFail($id);
    }

    public function create(PlatformTenantDTO $dto, ?AuthUser $actor = null): PlatformTenant
    {
        $payload = $dto->toArray();

        $payload['segment_id'] = $this->resolveSegmentIdForCreation($dto, $actor);

        if (! $payload['tenant_code']) {
            $payload['tenant_code'] = $this->generateTenantCode();
        }

        if ($payload['is_active'] === null) {
            unset($payload['is_active']);
        }

        if (empty($payload['plan_id'])) {
            $payload['plan_id'] = $this->resolveDefaultPlanId();
        }

        return PlatformTenant::query()->create($payload);
    }

    private function resolveSegmentIdForCreation(PlatformTenantDTO $dto, ?AuthUser $actor): ?string
    {
        if ($actor?->isSuperAdmin() !== true) {
            return $dto->segmentId;
        }

        $forcedSegment = AiPromptSegment::query()
            ->where('code', PlatformTenantBootstrapCatalogService::FORCED_SUPER_ADMIN_SEGMENT_CODE)
            ->first();

        if ($forcedSegment instanceof AiPromptSegment) {
            return $forcedSegment->id;
        }

        $generalSegment = AiPromptSegment::getGeneral();

        if ($generalSegment instanceof AiPromptSegment) {
            return $generalSegment->id;
        }

        return $dto->segmentId;
    }

    public function update(PlatformTenant $tenant, PlatformTenantDTO $dto): PlatformTenant
    {
        $payload = $dto->toArray();

        if ($payload['tenant_code'] === null) {
            unset($payload['tenant_code']);
        }

        if (array_key_exists('is_active', $payload) && $payload['is_active'] === null) {
            unset($payload['is_active']);
        }

        $tenant->fill($payload);
        $tenant->save();

        return $tenant->refresh();
    }

    public function delete(PlatformTenant $tenant): void
    {
        $tenant->delete();
    }

    public function restore(string $id): PlatformTenant
    {
        $tenant = $this->find($id, true);
        $tenant->restore();

        return $tenant->refresh();
    }

    public function forceDelete(string $id): void
    {
        $tenant = $this->find($id, true);
        $tenant->forceDelete();
    }

    public function toggleActive(string $id): PlatformTenant
    {
        $tenant = $this->find($id);
        $tenant->update(['is_active' => ! $tenant->is_active]);

        return $tenant->refresh();
    }

    private function sanitizeSortBy(string $sortBy): string
    {
        return in_array($sortBy, ['name', 'document', 'is_active', 'created_at', 'tenant_code', 'primary_email'], true)
            ? $sortBy
            : 'name';
    }

    private function sanitizeSortDirection(string $sortDirection): string
    {
        return strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';
    }

    private function sanitizePerPage(int $perPage): int
    {
        return min(max($perPage, 1), 100);
    }

    private function resolveDefaultPlanId(): ?string
    {
        $starterPlan = \Domain\Platform\Models\PlatformPlan::query()
            ->where('slug', 'starter')
            ->where('is_active', true)
            ->first();

        if ($starterPlan instanceof \Domain\Platform\Models\PlatformPlan) {
            return $starterPlan->id;
        }

        $anyPlan = \Domain\Platform\Models\PlatformPlan::query()
            ->where('is_active', true)
            ->first();

        return $anyPlan instanceof \Domain\Platform\Models\PlatformPlan ? $anyPlan->id : null;
    }

    private function generateTenantCode(): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $code = strtoupper(Str::random(8));
            if (! PlatformTenant::query()->where('tenant_code', $code)->exists()) {
                return $code;
            }
        }

        return strtoupper(Str::random(12));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<PlatformTenant>
     */
    private function buildFilteredQuery(array $filters = []): Builder
    {
        $query = PlatformTenant::query();

        if (! empty($filters['trashed'])) {
            $query->onlyTrashed();
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null) {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        if (! empty($filters['search'])) {
            $search = SearchSanitizer::likeContains((string) $filters['search']);
            $connectionName = $query->getModel()->getConnectionName() ?: config('database.default');
            $driver = (string) config("database.connections.$connectionName.driver", 'pgsql');
            $operator = $driver === 'sqlite' ? 'like' : 'ilike';
            $query->where(function ($sub) use ($search, $operator): void {
                $sub->where('name', $operator, $search)
                    ->orWhere('primary_email', $operator, $search)
                    ->orWhere('tenant_code', $operator, $search)
                    ->orWhere('document', $operator, $search);
            });
        }

        if (! empty($filters['created_from'])) {
            $query->whereDate('created_at', '>=', (string) $filters['created_from']);
        }

        if (! empty($filters['created_to'])) {
            $query->whereDate('created_at', '<=', (string) $filters['created_to']);
        }

        return $query;
    }
}
