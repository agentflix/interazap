<?php

declare(strict_types=1);

namespace Domain\Ai\Actions;

use Domain\Ai\Models\AiKnowledgeDocument;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * Casos de uso de leitura para documentos da base de conhecimento (RAG).
 *
 * Contexto: ponto de acesso ao model AiKnowledgeDocument com filtro
 * obrigatório por tenant_id e is_active = true.
 */
final class AiKnowledgeActions
{
    /**
     * Lista documentos ativos do tenant ordenados do mais recente ao mais antigo.
     *
     * @param  string  $tenantId  UUID do tenant.
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
     * Busca um documento ativo pelo ID dentro do tenant.
     *
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $id  UUID do documento.
     * @return AiKnowledgeDocument|null Documento encontrado ou null se não existir/inativo.
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
