<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Models\ChatMessage;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Action de listagem e consulta de mensagens de chat.
 *
 * Responsável por queries de leitura: paginação tradicional, cursor-based
 * e pré-carregamento de mensagens citadas (quoted).
 *
 * @category Actions
 */
final readonly class ListChatMessagesAction
{
    /**
     * Listar o histórico de mensagens de um ticket com suporte a paginação e filtros.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  Identificador UUID do ticket.
     * @param  array<string, mixed>  $filters  Critérios de filtragem (direction, type).
     * @return LengthAwarePaginator Coleção paginada de mensagens.
     */
    public function listByTicket(string $tenantId, string $ticketId, array $filters = [], bool $includeMetadata = false): LengthAwarePaginator
    {
        $columns = $this->messageColumns();

        $query = ChatMessage::query()
            ->select($columns)
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $ticketId)
            ->with(['user:id,name', 'extended']);

        if (! empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        return $query->orderBy('created_at', 'desc')->orderBy('id', 'desc')->paginate();
    }

    /**
     * Listar mensagens com paginação por cursor (before/after) para navegação incremental.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $ticketId  Identificador UUID do ticket.
     * @param  array<string, mixed>  $filters  Critérios de filtragem (direction, type).
     * @param  int  $limit  Limite máximo de mensagens retornadas.
     * @param  string|null  $after  Cursor para mensagens mais novas (ID de mensagem).
     * @param  string|null  $before  Cursor para mensagens mais antigas (ID de mensagem).
     * @param  string|null  $since  Cursor de resync determinístico (ID de mensagem ou timestamp ISO8601).
     * @return array{messages: \Illuminate\Support\Collection<int, ChatMessage>, meta: array<string, bool|int|string|null>}
     */
    public function listByTicketCursor(
        string $tenantId,
        string $ticketId,
        array $filters = [],
        int $limit = 30,
        ?string $after = null,
        ?string $before = null,
        ?string $since = null,
    ): array {
        $columns = $this->messageColumns();
        $safeLimit = max(1, min($limit, 100));

        $query = ChatMessage::query()
            ->select($columns)
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $ticketId)
            ->with(['user:id,name', 'extended']);

        if (! empty($filters['direction'])) {
            $query->where('direction', $filters['direction']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (is_string($before) && $before !== '') {
            $pivot = $this->resolveCursorMessage($tenantId, $ticketId, $before);
            if ($pivot instanceof ChatMessage) {
                $query->where(function ($cursorQuery) use ($pivot): void {
                    $cursorQuery
                        ->where('created_at', '<', $pivot->created_at)
                        ->orWhere(function ($tieBreakQuery) use ($pivot): void {
                            $tieBreakQuery
                                ->where('created_at', '=', $pivot->created_at)
                                ->where('id', '<', $pivot->id);
                        });
                });
            }
        }

        if (is_string($after) && $after !== '') {
            $pivot = $this->resolveCursorMessage($tenantId, $ticketId, $after);
            if ($pivot instanceof ChatMessage) {
                $this->applyAfterPivotConstraint($query, $pivot);
            }
        }

        if ((! is_string($after) || $after === '')
            && (! is_string($before) || $before === '')
            && is_string($since)
            && $since !== '') {
            $this->applySinceConstraint($query, $tenantId, $ticketId, $since);
        }

        $messages = $query
            ->orderBy('created_at', 'desc')
            ->orderBy('id', 'desc')
            ->limit($safeLimit)
            ->get();

        return [
            'messages' => $messages,
            'meta' => [
                'cursor_mode' => true,
                'limit' => $safeLimit,
                'before' => $before,
                'after' => $after,
                'since' => $since,
                'count' => $messages->count(),
                'has_more' => $messages->count() === $safeLimit,
            ],
        ];
    }

    /**
     * Pré-carregar mensagens citadas (quoted) em batch para evitar N+1.
     *
     * Extrai todos os IDs e external_ids de mensagens citadas da coleção,
     * faz uma única query e retorna um mapa para lookup rápido.
     *
     * @param  \Illuminate\Support\Collection<int, ChatMessage>  $messages
     * @return array<string, ChatMessage> Mapa indexado por ID e external_id.
     */
    public function prefetchQuotedMessages(\Illuminate\Support\Collection $messages): array
    {
        $quotedIds = [];
        $quotedExternalIds = [];
        $tenantIds = [];
        $ticketIds = [];

        foreach ($messages as $message) {
            $metadata = $message->metadata ?? [];
            $quotedMessageId = $metadata['quoted_message_id'] ?? null;
            $quotedExternalId = $metadata['message']['quoted'] ?? null;

            if (! empty($message->tenant_id)) {
                $tenantIds[] = (string) $message->tenant_id;
            }
            if (! empty($message->ticket_id)) {
                $ticketIds[] = (string) $message->ticket_id;
            }

            if ($quotedMessageId) {
                $quotedIds[] = $quotedMessageId;
            }
            if ($quotedExternalId) {
                $quotedExternalIds[] = $quotedExternalId;
            }
        }

        if ($quotedIds === [] && $quotedExternalIds === []) {
            return [];
        }

        $tenantIds = array_values(array_unique($tenantIds));
        $ticketIds = array_values(array_unique($ticketIds));

        if ($tenantIds === []) {
            return [];
        }

        $columns = ['id', 'external_id', 'tenant_id', 'content', 'type', 'direction'];

        $quotedMessages = ChatMessage::query()
            ->select($columns)
            ->with('extended:id,message_id,file_name,mime_type')
            ->whereIn('tenant_id', $tenantIds)
            ->when($ticketIds !== [], static function ($query) use ($ticketIds): void {
                $query->whereIn('ticket_id', $ticketIds);
            })
            ->where(function ($q) use ($quotedIds, $quotedExternalIds): void {
                if ($quotedIds !== []) {
                    $q->orWhereIn('id', $quotedIds);
                }
                if ($quotedExternalIds !== []) {
                    $q->orWhereIn('external_id', $quotedExternalIds);
                }
            })
            ->get();

        $map = [];
        foreach ($quotedMessages as $msg) {
            $map[$msg->id] = $msg;
            if ($msg->external_id) {
                $map[$msg->external_id] = $msg;
            }
        }

        return $map;
    }

    /**
     * Colunas padrão para queries de mensagens.
     *
     * @return list<string>
     */
    private function messageColumns(): array
    {
        return [
            'id',
            'tenant_id',
            'ticket_id',
            'user_id',
            'contact_id',
            'direction',
            'type',
            'content',
            'status',
            'external_id',
            'is_from_contact',
            'source',
            'sent_at',
            'delivered_at',
            'read_at',
            'is_deleted',
            'deleted_at',
            'deleted_by',
            'metadata',
            'created_at',
            'updated_at',
        ];
    }

    private function applySinceConstraint(mixed $query, string $tenantId, string $ticketId, string $since): void
    {
        $pivot = Str::isUuid($since)
            ? $this->resolveCursorMessage($tenantId, $ticketId, $since)
            : null;

        if ($pivot instanceof ChatMessage) {
            $this->applyAfterPivotConstraint($query, $pivot);

            return;
        }

        try {
            $sinceAt = \Illuminate\Support\Facades\Date::parse($since);
        } catch (\Throwable) {
            return;
        }

        $query->where('created_at', '>', $sinceAt);
    }

    private function applyAfterPivotConstraint(mixed $query, ChatMessage $pivot): void
    {
        $query->where(function ($cursorQuery) use ($pivot): void {
            $cursorQuery
                ->where('created_at', '>', $pivot->created_at)
                ->orWhere(function ($tieBreakQuery) use ($pivot): void {
                    $tieBreakQuery
                        ->where('created_at', '=', $pivot->created_at)
                        ->where('id', '>', $pivot->id);
                });
        });
    }

    /**
     * Resolver mensagem pivô do cursor para navegação before/after.
     */
    private function resolveCursorMessage(string $tenantId, string $ticketId, string $cursor): ?ChatMessage
    {
        return ChatMessage::query()
            ->select(['id', 'created_at'])
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $ticketId)
            ->where('id', $cursor)
            ->first();
    }
}
