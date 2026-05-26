<?php

declare(strict_types=1);

namespace Domain\Platform\Actions;

use Domain\Auth\Actions\AuthUserActions;
use Domain\Auth\DTOs\AuthUserDTO;
use Domain\Auth\Models\AuthRole;
use Domain\CRM\Actions\CRMContactActions;
use Domain\CRM\DTOs\CRMContactDTO;
use Domain\Platform\DTOs\PlatformTenantDTO;
use Domain\Platform\Models\PlatformLead;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Actions administrativas para listagem e conversão de leads da plataforma.
 */
final class PlatformLeadAdminActions
{
    private const SORTABLE_COLUMNS = ['name', 'email', 'created_at'];

    public function __construct(
        private readonly PlatformTenantActions $platformTenantActions,
        private readonly AuthUserActions $authUserActions,
        private readonly CRMContactActions $crmContactActions,
        private readonly PlatformTenantBootstrapAction $platformTenantBootstrapAction,
        private readonly DatabaseManager $database,
    ) {}

    /**
     * Lista leads com filtros, ordenação e paginação.
     *
     * @param  array<string, mixed>  $filters  Filtros de busca e paginação.
     * @return LengthAwarePaginator Lista paginada de leads.
     */
    public function list(array $filters): LengthAwarePaginator
    {
        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 15;
        $sortBy = $this->sanitizeSortBy((string) ($filters['sort_by'] ?? 'created_at'));
        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return $this->buildFilteredQuery($filters)
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * Retorna query de leads ordenada para exportação (sem paginação).
     *
     * @param  array<string, mixed>  $filters  Filtros de busca e ordenação.
     * @return Builder<PlatformLead> Query pronta para iteração via cursor.
     */
    public function queryForExport(array $filters): Builder
    {
        $sortBy = $this->sanitizeSortBy((string) ($filters['sort_by'] ?? 'created_at'));
        $sortDir = strtolower((string) ($filters['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

        return $this->buildFilteredQuery($filters)
            ->orderBy($sortBy, $sortDir);
    }

    /**
     * Converte um lead em tenant, cria usuário Gerente, executa bootstrap e cria contato CRM.
     *
     * @param  PlatformLead  $lead  Lead a ser convertido.
     * @param  array{name:string,email:string,phone:string,document?:string|null,plan_id?:string|null}  $payload  Dados de conversão.
     * @return PlatformLead Lead original atualizado.
     */
    public function convert(PlatformLead $lead, array $payload): PlatformLead
    {
        return $this->database->transaction(function () use ($lead, $payload): PlatformLead {
            $tenant = $this->platformTenantActions->create(
                PlatformTenantDTO::fromArray([
                    'name' => $payload['name'],
                    'email' => $payload['email'],
                    'document' => $payload['document'] ?? null,
                    'phone' => $payload['phone'],
                    'plan_id' => $payload['plan_id'] ?? null,
                ])
            );

            $this->authUserActions->create(
                AuthUserDTO::fromArray([
                    'name' => $payload['name'],
                    'email' => $payload['email'],
                    'phone' => $payload['phone'],
                    'password' => Str::password(16),
                    'tenant_id' => $tenant->id,
                    'role' => AuthRole::INQUILINO_NAME,
                    'is_active' => true,
                ])
            );

            $this->platformTenantBootstrapAction->execute($tenant);

            $this->crmContactActions->create(
                $tenant->id,
                CRMContactDTO::fromArray([
                    'name' => $payload['name'],
                    'email' => $payload['email'],
                    'phone' => $payload['phone'],
                    'document' => $payload['document'] ?? null,
                    'is_active' => true,
                ])
            );

            return $lead;
        });
    }

    /**
     * Constrói a query de leads com filtros de busca aplicados.
     *
     * @param  array<string, mixed>  $filters  Filtros de busca.
     * @return Builder<PlatformLead> Query filtrada.
     */
    private function buildFilteredQuery(array $filters): Builder
    {
        $query = PlatformLead::query();

        $search = trim((string) ($filters['search'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%");
            });
        }

        return $query;
    }

    /**
     * Busca um lead pelo ID ou lança exceção se não encontrado.
     *
     * @param  string  $id  UUID do lead.
     * @return PlatformLead Lead encontrado.
     *
     * @throws \DomainException Quando o lead não é encontrado.
     */
    public function find(string $id): PlatformLead
    {
        $lead = PlatformLead::find($id);

        if (! $lead) {
            throw new \DomainException('Lead não encontrado.', 404);
        }

        return $lead;
    }

    /**
     * Sanitiza o campo de ordenação, retornando o padrão se inválido.
     *
     * @param  string  $sortBy  Nome da coluna solicitada.
     * @return string Coluna válida para ordenação.
     */
    private function sanitizeSortBy(string $sortBy): string
    {
        if (! in_array($sortBy, self::SORTABLE_COLUMNS, true)) {
            return 'created_at';
        }

        return $sortBy;
    }
}
