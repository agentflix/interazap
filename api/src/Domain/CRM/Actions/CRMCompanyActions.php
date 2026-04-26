<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\DTOs\CRMCompanyDTO;
use Domain\CRM\Models\CRMCompany;
use Domain\Shared\Concerns\GuardsUniqueName;
use Domain\Shared\Support\SearchSanitizer;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Casos de uso para empresas do CRM.
 */
final class CRMCompanyActions
{
    use GuardsUniqueName;

    /**
     * Lista empresas do CRM com filtros de busca e status.
     *
     * @param  string  $tenantId  ID do tenant
     * @param  string|null  $searchTerm  Termo de busca (name, document, email)
     * @param  bool|null  $isActive  Filtro de status ativo/inativo
     */
    public function list(string $tenantId, ?string $searchTerm = null, ?bool $isActive = null): LengthAwarePaginator
    {
        return CRMCompany::query()
            ->where('tenant_id', $tenantId)
            ->when($searchTerm, fn ($q, $val) => $q->where(fn ($query) => $query->where('name', 'ilike', SearchSanitizer::likeContains($val))
                ->orWhere('document', 'ilike', SearchSanitizer::likeContains($val))
                ->orWhere('email', 'ilike', SearchSanitizer::likeContains($val))))
            ->when($isActive !== null, fn ($q) => $q->where('is_active', $isActive))
            ->with(['contacts', 'customFieldValues'])
            ->orderBy('name')
            ->paginate();
    }

    public function create(string $tenantId, CRMCompanyDTO $dto): CRMCompany
    {
        $this->guardUniqueName(CRMCompany::class, $tenantId, $dto->name, 'Empresa já cadastrada para este tenant.');

        return CRMCompany::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            ...$dto->toArray(),
        ]);
    }

    public function update(string $tenantId, string $id, CRMCompanyDTO $dto): CRMCompany
    {
        $company = $this->find($tenantId, $id);
        if ($company->name !== $dto->name) {
            $this->guardUniqueName(CRMCompany::class, $tenantId, $dto->name, 'Empresa já cadastrada para este tenant.');
        }

        $company->fill($dto->toArray());
        $company->save();

        return $company->load(['contacts', 'customFieldValues']);
    }

    public function delete(string $tenantId, string $id): void
    {
        $company = $this->find($tenantId, $id);
        $company->delete();
    }

    public function find(string $tenantId, string $id): CRMCompany
    {
        return CRMCompany::query()
            ->where('tenant_id', $tenantId)
            ->with(['contacts', 'customFieldValues'])
            ->findOrFail($id);
    }
}
