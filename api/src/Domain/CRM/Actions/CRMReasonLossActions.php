<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\DTOs\CRMReasonLossDTO;
use Domain\CRM\Models\CRMReasonLoss;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Casos de uso para motivos de perda.
 */
final class CRMReasonLossActions
{
    /**
     * Lista todos os motivos de perda ativos do tenant sem paginação.
     *
     * @return Collection<int, CRMReasonLoss>
     */
    public function all(string $tenantId): Collection
    {
        return CRMReasonLoss::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get();
    }

    /**
     * Lista motivos de perda com paginação, filtrando por status quando informado.
     *
     * @param  bool|null  $active  Quando informado, filtra apenas ativos (true) ou inativos (false)
     */
    public function list(string $tenantId, ?bool $active = null): LengthAwarePaginator
    {
        $query = CRMReasonLoss::query()
            ->where('tenant_id', $tenantId);

        if ($active !== null) {
            $query->where('is_active', $active);
        }

        return $query->orderBy('position')->orderBy('name')->paginate();
    }

    /**
     * Cria um motivo de perda garantindo unicidade de nome (case-insensitive) no tenant.
     *
     * @throws \Illuminate\Validation\ValidationException Quando o nome já existe
     */
    public function create(string $tenantId, CRMReasonLossDTO $dto): CRMReasonLoss
    {
        $this->guardUniqueName($tenantId, $dto->name);

        return CRMReasonLoss::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            ...$dto->toArray(),
        ]);
    }

    /** Atualiza um motivo de perda, verificando unicidade de nome se alterado. */
    public function update(string $tenantId, string $id, CRMReasonLossDTO $dto): CRMReasonLoss
    {
        $reason = $this->find($tenantId, $id);
        if (mb_strtolower(trim($reason->name)) !== mb_strtolower(trim($dto->name))) {
            $this->guardUniqueName($tenantId, $dto->name);
        }

        $reason->fill($dto->toArray());
        $reason->save();

        return $reason;
    }

    /** Remove um motivo de perda pelo ID. */
    public function delete(string $tenantId, string $id): void
    {
        $reason = $this->find($tenantId, $id);
        $reason->delete();
    }

    /** Retorna um motivo de perda pelo ID, lançando 404 se não pertencer ao tenant. */
    public function find(string $tenantId, string $id): CRMReasonLoss
    {
        return CRMReasonLoss::query()
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }

    private function guardUniqueName(string $tenantId, string $name): void
    {
        $normalizedName = mb_strtolower(trim($name));

        $exists = CRMReasonLoss::query()
            ->where('tenant_id', $tenantId)
            ->whereRaw('LOWER(TRIM(name)) = ?', [$normalizedName])
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'name' => ['Motivo de perda já existe para este tenant.'],
            ]);
        }
    }
}
