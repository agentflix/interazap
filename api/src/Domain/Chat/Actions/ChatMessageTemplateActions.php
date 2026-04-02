<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Models\ChatMessageTemplate;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Casos de Uso para Templates de Mensagens.
 *
 * Centraliza a gestão de moldes de mensagens pré-definidos para o tenant.
 *
 * @category Actions
 */
final class ChatMessageTemplateActions
{
    /**
     * Listar todos os templates do tenant com paginação.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @return LengthAwarePaginator Paginador com registros de templates.
     */
    public function list(string $tenantId): LengthAwarePaginator
    {
        return ChatMessageTemplate::query()
            ->where('tenant_id', $tenantId)
            ->latest()
            ->paginate();
    }

    /**
     * Criar um novo template formatado.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<string, mixed>  $data  Dados do template (name, type, body).
     * @return ChatMessageTemplate Modelo criado.
     */
    public function create(string $tenantId, array $data): ChatMessageTemplate
    {
        return ChatMessageTemplate::query()->create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);
    }
}
