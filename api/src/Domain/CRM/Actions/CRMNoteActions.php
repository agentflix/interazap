<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Domain\CRM\DTOs\CRMNoteDTO;
use Domain\CRM\Models\CRMNote;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Casos de uso para notas CRM.
 */
final class CRMNoteActions
{
    /**
     * Lista as notas de uma entidade (contato, empresa ou negociação) com paginação.
     *
     * @param  string  $entityType  Tipo polimórfico da entidade
     * @param  string  $entityId  ID da entidade
     */
    public function list(string $tenantId, string $entityType, string $entityId): LengthAwarePaginator
    {
        return CRMNote::query()
            ->where('tenant_id', $tenantId)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->with('author')
            ->orderByDesc('created_at')
            ->paginate();
    }

    /** Cria uma nota associada a uma entidade CRM. */
    public function create(string $tenantId, string $authUserId, CRMNoteDTO $dto): CRMNote
    {
        return CRMNote::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'auth_user_id' => $authUserId,
            ...$dto->toArray(),
        ]);
    }
}
