<?php

declare(strict_types=1);

namespace Domain\Ai\Actions;

use Domain\Ai\Models\AiKnowledgeDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Actions para documentos de conhecimento (RAG).
 */
final class AiKnowledgeActions
{
    /**
     * Listar documentos ativos do tenant.
     *
     * @return LengthAwarePaginator<int, AiKnowledgeDocument>
     */
    public function list(string $tenantId): LengthAwarePaginator
    {
        return AiKnowledgeDocument::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderByDesc('created_at')
            ->paginate(20);
    }

    /**
     * Buscar documento ativo por ID.
     */
    public function findActive(string $tenantId, string $id): ?AiKnowledgeDocument
    {
        return AiKnowledgeDocument::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $id)
            ->where('is_active', true)
            ->first();
    }
}
