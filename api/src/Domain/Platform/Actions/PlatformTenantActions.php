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
use RuntimeException;

/**
 * Ações para gerenciamento de tenants da plataforma.
 *
 * Concentra listagem, criação, atualização, exclusão, restauração e toggle de status de tenants.
 */
final class PlatformTenantActions
{
    private const PROTECTED_TENANT_DELETE_MESSAGE = 'Empresa principal InteraZap não pode ser excluída.';

    /**
     * Lista tenants com filtros, ordenação e paginação.
     *
     * @param  array<string, mixed>  $filters  Filtros de busca, status, datas e paginação.
     * @return LengthAwarePaginator Lista paginada de tenants.
     */
    public function list(array $filters = []): LengthAwarePaginator
    {
        $query = $this->buildFilteredQuery($filters);

        $sortBy = $this->sanitizeSortBy($filters['sort_by'] ?? 'name');
        $sortDir = $this->sanitizeSortDirection($filters['sort_dir'] ?? 'asc');
        $perPage = $this->sanitizePerPage((int) ($filters['per_page'] ?? 15));

        return $query->with('segment')->orderBy($sortBy, $sortDir)->paginate($perPage);
    }

    /**
     * Retorna query de tenants ordenada para exportação (sem paginação).
     *
     * @param  array<string, mixed>  $filters  Filtros de busca e ordenação.
     * @return Builder<PlatformTenant> Query pronta para iteração via cursor.
     */
    public function queryForExport(array $filters = []): Builder
    {
        $query = $this->buildFilteredQuery($filters);
        $sortBy = $this->sanitizeSortBy($filters['sort_by'] ?? 'name');
        $sortDir = $this->sanitizeSortDirection($filters['sort_dir'] ?? 'asc');

        return $query->orderBy($sortBy, $sortDir);
    }

    /**
     * Busca um tenant pelo ID, com opção de incluir registros excluídos.
     *
     * @param  string  $id  UUID do tenant.
     * @param  bool  $withTrashed  Se verdadeiro, inclui registros soft-deleted.
     * @return PlatformTenant Tenant encontrado.
     */
    public function find(string $id, bool $withTrashed = false): PlatformTenant
    {
        $query = PlatformTenant::query();

        if ($withTrashed) {
            $query->withTrashed();
        }

        return $query->findOrFail($id);
    }

    /**
     * Cria um novo tenant, resolvendo segmento, código e plano padrão automaticamente.
     *
     * @param  PlatformTenantDTO  $dto  Dados do tenant.
     * @param  AuthUser|null  $actor  Usuário que executa a ação (super admin ou null).
     * @return PlatformTenant Tenant criado.
     */
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

    /**
     * Resolve o segment_id para criação do tenant, priorizando o informado no DTO
     * ou buscando o segmento SAAS/GENERAL para super admins.
     *
     * @param  PlatformTenantDTO  $dto  Dados do tenant.
     * @param  AuthUser|null  $actor  Usuário executando a ação.
     * @return string|null ID do segmento resolvido.
     */
    private function resolveSegmentIdForCreation(PlatformTenantDTO $dto, ?AuthUser $actor): ?string
    {
        if ($actor?->isSuperAdmin() !== true) {
            return $dto->segmentId;
        }

        if ($dto->segmentId !== null) {
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

        return null;
    }

    /**
     * Atualiza os dados de um tenant existente.
     *
     * @param  PlatformTenant  $tenant  Tenant a ser atualizado.
     * @param  PlatformTenantDTO  $dto  Novos dados do tenant.
     * @return PlatformTenant Tenant atualizado.
     */
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

    /**
     * Executa soft delete do tenant, verificando se não é o tenant principal protegido.
     *
     * @param  PlatformTenant  $tenant  Tenant a ser excluído.
     *
     * @throws \RuntimeException Quando o tenant é o principal protegido.
     */
    public function delete(PlatformTenant $tenant): void
    {
        $this->assertTenantCanBeDeleted($tenant);

        $tenant->delete();
    }

    /**
     * Restaura um tenant previamente excluído (soft delete).
     *
     * @param  string  $id  UUID do tenant.
     * @return PlatformTenant Tenant restaurado.
     */
    public function restore(string $id): PlatformTenant
    {
        $tenant = $this->find($id, true);
        $tenant->restore();

        return $tenant->refresh();
    }

    /**
     * Remove permanentemente o tenant do banco de dados.
     *
     * @param  string  $id  UUID do tenant.
     *
     * @throws \RuntimeException Quando o tenant é o principal protegido.
     */
    public function forceDelete(string $id): void
    {
        $tenant = $this->find($id, true);
        $this->assertTenantCanBeDeleted($tenant);

        $tenant->forceDelete();
    }

    /**
     * Garante que o tenant pode ser excluído, bloqueando o tenant principal protegido.
     *
     * @throws \RuntimeException Quando o tenant é o principal protegido.
     */
    private function assertTenantCanBeDeleted(PlatformTenant $tenant): void
    {
        if ($tenant->isProtectedDefaultTenant()) {
            throw new RuntimeException(self::PROTECTED_TENANT_DELETE_MESSAGE);
        }
    }

    /**
     * Alterna o status ativo/inativo do tenant.
     *
     * @param  string  $id  UUID do tenant.
     * @return PlatformTenant Tenant com status atualizado.
     */
    public function toggleActive(string $id): PlatformTenant
    {
        $tenant = $this->find($id);
        $tenant->update(['is_active' => ! $tenant->is_active]);

        return $tenant->refresh();
    }

    /**
     * Sanitiza o campo de ordenação, aceitando apenas colunas permitidas.
     */
    private function sanitizeSortBy(string $sortBy): string
    {
        return in_array($sortBy, ['name', 'document', 'is_active', 'created_at', 'tenant_code', 'primary_email'], true)
            ? $sortBy
            : 'name';
    }

    /**
     * Sanitiza a direção de ordenação para 'asc' ou 'desc'.
     */
    private function sanitizeSortDirection(string $sortDirection): string
    {
        return strtolower($sortDirection) === 'desc' ? 'desc' : 'asc';
    }

    /**
     * Garante que o número de itens por página fique entre 1 e 100.
     */
    private function sanitizePerPage(int $perPage): int
    {
        return min(max($perPage, 1), 100);
    }

    /**
     * Resolve o ID do plano padrão: tenta 'starter', depois qualquer plano ativo.
     *
     * @return string|null UUID do plano ou null se nenhum ativo existir.
     */
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

    /**
     * Gera um código único de tenant com 8 caracteres (até 12 em caso de colisão).
     */
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
     * Constrói a query de tenants com filtros aplicados.
     *
     * @param  array<string, mixed>  $filters  Filtros de busca e status.
     * @return Builder<PlatformTenant> Query filtrada.
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
