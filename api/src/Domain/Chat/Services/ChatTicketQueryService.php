<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Chat\Models\ChatTicket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Builds ticket list and count queries for chat inbox views.
 */
final class ChatTicketQueryService
{
    /** TTL em segundos para o cache de contagens de tickets por status. */
    private const int COUNTS_CACHE_TTL_SECONDS = 15;

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, ChatTicket>
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
                    ->select([
                        'id',
                        'tenant_id',
                        'ticket_id',
                        'user_id',
                        'contact_id',
                        'content',
                        'type',
                        'direction',
                        'status',
                        'external_id',
                        'metadata',
                        'created_at',
                        'updated_at',
                    ])
                    ->with(['user:id,name', 'extended']),
            ])
            ->withCount([
                'messages as unread_count' => fn ($messageQuery) => $messageQuery
                    ->where('direction', 'incoming')
                    ->whereNull('read_at'),
            ])
            ->where('tenant_id', $tenantId);

        $this->applyFilters($query, $filters);

        $groupByContact = (bool) ($filters['group_by_contact'] ?? true);

        if ($groupByContact) {
            $rankedTickets = ChatTicket::query()
                ->select('id')
                ->selectRaw('ROW_NUMBER() OVER (PARTITION BY COALESCE(contact_id::text, remote_jid, id::text) ORDER BY COALESCE(last_message_at, updated_at, created_at) DESC, updated_at DESC, created_at DESC, id DESC) as row_num')
                ->where('tenant_id', $tenantId);

            $this->applyFilters($rankedTickets, $filters);

            $latestTicketIds = DB::query()
                ->fromSub($rankedTickets->toBase(), 'ranked_tickets')
                ->select('id')
                ->where('row_num', 1);

            $query->whereIn('chat_tickets.id', $latestTicketIds);
        }

        $sortBy = (string) ($filters['sort_by'] ?? 'last_message_at');
        $sortColumn = $sortBy === 'sentiment_score' ? 'sentiment_score' : 'last_message_at';

        return $query
            ->orderByDesc($sortColumn)
            ->orderByDesc('updated_at')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * @return array<string, int>
     */
    public function counts(string $tenantId): array
    {
        return Cache::remember("chat_counts:{$tenantId}", self::COUNTS_CACHE_TTL_SECONDS, function () use ($tenantId): array {
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

        if (! empty($filters['user_id'])) {
            $query->where('assigned_to', $filters['user_id']);
        }

        if (! empty($filters['agent_id'])) {
            $query->where('assigned_to', $filters['agent_id']);
        }

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['search'])) {
            $term = (string) $filters['search'];
            $query->where(function (Builder $builder) use ($term): void {
                $builder->whereHas('extended', function (Builder $extQuery) use ($term): void {
                    $extQuery->where('subject', 'like', "%{$term}%");
                })
                    ->orWhere('remote_jid', 'like', "%{$term}%")
                    ->orWhere('push_name', 'like', "%{$term}%")
                    ->orWhere('protocol', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "%{$term}%")
                    ->orWhere('phone_e164', 'like', "%{$term}%")
                    ->orWhereHas('contact', function (Builder $contactQuery) use ($term): void {
                        $contactQuery->where('name', 'like', "%{$term}%")
                            ->orWhere('phone', 'like', "%{$term}%");
                    })
                    ->orWhereHas('user', function (Builder $userQuery) use ($term): void {
                        $userQuery->where('name', 'like', "%{$term}%");
                    });
            });
        }

        if (! empty($filters['sentiment'])) {
            $query->where('sentiment', (string) $filters['sentiment']);
        }
    }
}
