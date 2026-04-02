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
