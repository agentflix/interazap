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
     * Listar todos os templates do tenant com paginação e filtros.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<string, mixed>  $filters  Critérios de busca.
     * @return LengthAwarePaginator Paginador com registros de templates.
     */
    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = ChatMessageTemplate::query()->where('tenant_id', $tenantId);

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'ilike', '%'.$search.'%')
                    ->orWhere('shortcut', 'ilike', '%'.$search.'%');
            });
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['chat_instance_id'])) {
            $query->where('chat_instance_id', $filters['chat_instance_id']);
        }

        if (isset($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }

        if (isset($filters['is_active'])) {
            $query->where('is_active', filter_var($filters['is_active'], FILTER_VALIDATE_BOOLEAN));
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
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
