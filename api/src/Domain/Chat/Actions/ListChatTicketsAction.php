<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Models\ChatTicket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;

/**
 * Listar tickets com paginação, filtros e contadores de status.
 *
 * Responsabilidades:
 * - Listar tickets com eager loading otimizado
 * - Aplicar filtros avançados (status, contact, search, date range)
 * - Retornar contagem de tickets por status
 * - Inicializar view com dados agregados
 */
final class ListChatTicketsAction
{
    /**
     * Listar tickets com paginação e filtros avançados.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<string, mixed>  $filters  Critérios de busca.
     * @return LengthAwarePaginator Resultados paginados.
     */
    public function list(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = ChatTicket::query()
            ->with([
                'extended',
                'evaluation:id,ticket_id,rating,comment,submitted_at',
                'instance:id,name,provider',
                'contact:id,name,phone,whatsapp,email,avatar_url,tenant_id',
                'user:id,name',
                'latestMessage' => fn ($relation) => $relation
                    ->select(['id', 'tenant_id', 'ticket_id', 'user_id', 'contact_id', 'content', 'type', 'direction', 'status', 'external_id', 'metadata', 'created_at', 'updated_at'])
                    ->with(['user:id,name', 'extended']),
            ])
            ->withCount([
                'messages as unread_count' => fn ($messageQuery) => $messageQuery
                    ->where('direction', 'incoming')
                    ->whereNull('read_at'),
            ])
            ->where('tenant_id', $tenantId);

        $this->applyFilters($query, $filters);

        if ((bool) ($filters['group_by_contact'] ?? true)) {
            $this->applyGroupByContact($query, $tenantId, $filters);
        }

        $sortColumn = ($filters['sort_by'] ?? 'last_message_at') === 'sentiment_score' ? 'sentiment_score' : 'last_message_at';

        return $query
            ->orderByDesc($sortColumn)
            ->orderByDesc('updated_at')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Obter estatísticas globais de tickets por status.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @return array<string, int> Mapa de contagens por status.
     */
    public function counts(string $tenantId): array
    {
        return Cache::remember("chat_counts:{$tenantId}", 15, function () use ($tenantId): array {
            $counts = ChatTicket::query()
                ->where('tenant_id', $tenantId)
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status')
                ->toArray();

            $all = array_sum($counts);

            return [
                'all' => $all,
                'pending' => $counts['pending'] ?? 0,
                'open' => ($counts['open'] ?? 0) + ($counts['in_progress'] ?? 0),
                'in_progress' => $counts['in_progress'] ?? 0,
                'closed' => $counts['closed'] ?? 0,
            ];
        });
    }

    /**
     * Carregar payload inicial de inbox com tickets, contadores e preferências.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $userId  ID do usuário autenticado.
     * @param  array<string, mixed>  $filters  Filtros opcionais.
     * @return array{tickets: array, counts: array, user_preferences: array}
     */
    public function init(string $tenantId, string $userId, array $filters = []): array
    {
        $tickets = $this->list($tenantId, $filters);
        $counts = $this->counts($tenantId);

        return [
            'tickets' => [
                'data' => $tickets->items(),
                'meta' => [
                    'current_page' => $tickets->currentPage(),
                    'last_page' => $tickets->lastPage(),
                    'per_page' => $tickets->perPage(),
                    'total' => $tickets->total(),
                    'has_more' => $tickets->hasMorePages(),
                ],
                'links' => [
                    'first' => $tickets->url(1),
                    'last' => $tickets->url($tickets->lastPage()),
                    'prev' => $tickets->previousPageUrl(),
                    'next' => $tickets->nextPageUrl(),
                ],
            ],
            'counts' => $counts,
            'user_preferences' => [
                'user_id' => $userId,
                'notifications_enabled' => true,
                'sound_enabled' => true,
                'enter_to_send' => true,
                'show_internal_events' => false,
            ],
        ];
    }

    /**
     * Aplicar filtros à query de listagem.
     *
     * @param  Builder<ChatTicket>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['contact_id'])) {
            $query->where('contact_id', $filters['contact_id']);
        }

        if (! empty($filters['instance_id'])) {
            $query->where('instance_id', $filters['instance_id']);
        }

        if (! empty($filters['user_id']) || ! empty($filters['agent_id'])) {
            $userId = $filters['user_id'] ?? $filters['agent_id'];
            $query->where('assigned_to', $userId);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['sentiment'])) {
            $query->where('sentiment', (string) $filters['sentiment']);
        }

        if (! empty($filters['search'])) {
            $this->applySearchFilter($query, $filters['search']);
        }
    }

    /**
     * Aplicar filtro de busca de texto livre.
     *
     * @param  Builder<ChatTicket>  $query
     */
    private function applySearchFilter(Builder $query, string $term): void
    {
        $query->where(function (Builder $builder) use ($term): void {
            $builder->whereHas('extended', fn (Builder $q) => $q->where('subject', 'like', "%{$term}%"))
                ->orWhere('remote_jid', 'like', "%{$term}%")
                ->orWhere('push_name', 'like', "%{$term}%")
                ->orWhere('protocol', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('phone_e164', 'like', "%{$term}%")
                ->orWhereHas('contact', fn (Builder $q) => $q->where('name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%"))
                ->orWhereHas('user', fn (Builder $q) => $q->where('name', 'like', "%{$term}%"));
        });
    }

    /**
     * Aplicar agrupamento por contato (pega apenas o ticket mais recente por contato).
     *
     * @param  Builder<ChatTicket>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyGroupByContact(Builder $query, string $tenantId, array $filters): void
    {
        $rankedTickets = ChatTicket::query()
            ->select('id')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY COALESCE(contact_id::text, remote_jid, id::text) ORDER BY COALESCE(last_message_at, updated_at, created_at) DESC, updated_at DESC, created_at DESC, id DESC) as row_num')
            ->where('tenant_id', $tenantId);

        $this->applyFilters($rankedTickets, $filters);

        $latestTicketIds = \Illuminate\Support\Facades\DB::query()
            ->fromSub($rankedTickets->toBase(), 'ranked_tickets')
            ->select('id')
            ->where('row_num', 1);

        $query->whereIn('chat_tickets.id', $latestTicketIds);
    }
}
