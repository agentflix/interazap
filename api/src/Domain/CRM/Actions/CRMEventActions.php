<?php

declare(strict_types=1);

namespace Domain\CRM\Actions;

use Carbon\CarbonImmutable;
use Domain\CRM\DTOs\CRMEventDTO;
use Domain\CRM\Models\CRMEvent;
use Domain\CRM\Models\CRMEventLink;
use Domain\CRM\Models\CRMEventParticipant;
use Domain\Shared\Support\SearchSanitizer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Casos de uso para eventos da agenda CRM.
 */
final class CRMEventActions
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function list(string $tenantId, array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = CRMEvent::query()
            ->where('tenant_id', $tenantId)
            ->with(['user', 'links', 'participants', 'reminders']);

        if (! empty($filters['search'])) {
            $query->where('title', 'ilike', SearchSanitizer::likeContains($filters['search']));
        }

        if (! empty($filters['start_date'])) {
            $query->where('starts_at', '>=', CarbonImmutable::parse($filters['start_date']));
        }

        if (! empty($filters['end_date'])) {
            $query->where('starts_at', '<=', CarbonImmutable::parse($filters['end_date']));
        }

        if (! empty($filters['user_id'])) {
            $query->where('auth_user_id', $filters['user_id']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (! empty($filters['participant_id'])) {
            $query->whereHas('participants', function ($participantQuery) use ($filters): void {
                $participantQuery->where('auth_user_id', $filters['participant_id']);
            });
        }

        if (! empty($filters['linkable_type'])) {
            $linkableType = CRMEventLink::resolveLinkableType((string) $filters['linkable_type'])
                ?? ($filters['linkable_type'] === 'negotiation' ? CRMEventLink::resolveLinkableType('deal') : null)
                ?? (is_string($filters['linkable_type']) ? $filters['linkable_type'] : null);

            if ($linkableType !== null) {
                $query->whereHas('links', function ($linkQuery) use ($linkableType, $filters): void {
                    $linkQuery->where('linkable_type', $linkableType);

                    if (! empty($filters['linkable_id'])) {
                        $linkQuery->where('linkable_id', $filters['linkable_id']);
                    }
                });
            }
        } elseif (! empty($filters['linkable_id'])) {
            $query->whereHas('links', function ($linkQuery) use ($filters): void {
                $linkQuery->where('linkable_id', $filters['linkable_id']);
            });
        }

        if (array_key_exists('is_all_day', $filters)) {
            $isAllDay = filter_var($filters['is_all_day'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($isAllDay !== null) {
                $query->where('is_all_day', $isAllDay);
            }
        }

        if (! empty($filters['recurrence'])) {
            $query->where('recurrence', $filters['recurrence']);
        }

        if (! empty($filters['location'])) {
            $query->where('location', 'ilike', SearchSanitizer::likeContains($filters['location']));
        }

        if (array_key_exists('has_reminders', $filters)) {
            $hasReminders = filter_var($filters['has_reminders'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($hasReminders === true) {
                $query->whereHas('reminders');
            }

            if ($hasReminders === false) {
                $query->whereDoesntHave('reminders');
            }
        }

        $sortBy = $filters['sort_by'] ?? 'starts_at';
        $sortDir = $filters['sort_dir'] ?? 'asc';

        return $query
            ->orderBy($sortBy, $sortDir)
            ->paginate($perPage);
    }

    public function calendar(string $tenantId, CarbonImmutable $start, CarbonImmutable $end, ?string $userId = null): Collection
    {
        $query = CRMEvent::query()
            ->where('tenant_id', $tenantId)
            ->where('starts_at', '>=', $start)
            ->where('starts_at', '<=', $end)
            ->where('status', '!=', CRMEvent::STATUS_CANCELLED)
            ->orderBy('starts_at')
            ->with(['user', 'participants']);

        if ($userId !== null) {
            $query->where(function ($q) use ($userId): void {
                $q->where('auth_user_id', $userId)
                    ->orWhereHas('participants', fn ($participant) => $participant->where('auth_user_id', $userId));
            });
        }

        /** @var Collection<int, CRMEvent> */
        return $query->get();
    }

    public function find(string $tenantId, string $id): CRMEvent
    {
        return CRMEvent::query()
            ->where('tenant_id', $tenantId)
            ->with(['user', 'links', 'participants', 'reminders'])
            ->findOrFail($id);
    }

    public function create(CRMEventDTO $dto): CRMEvent
    {
        return DB::transaction(function () use ($dto): CRMEvent {
            $event = CRMEvent::query()->create([
                'id' => $dto->id ?? (string) Str::orderedUuid(),
                ...$dto->toArray(),
            ]);

            $this->syncLinks($event, $dto->links);
            $this->syncParticipants($event, $dto->participants);
            $this->syncReminders($event, $dto->reminders);

            return $event->load(['user', 'links', 'participants', 'reminders']);
        });
    }

    public function update(string $tenantId, string $id, CRMEventDTO $dto): CRMEvent
    {
        return DB::transaction(function () use ($tenantId, $id, $dto): CRMEvent {
            $event = $this->find($tenantId, $id);
            $data = $dto->toArray();
            unset($data['tenant_id']);

            $event->fill($data);
            $event->save();

            $this->syncLinks($event, $dto->links);
            $this->syncParticipants($event, $dto->participants);
            $this->syncReminders($event, $dto->reminders);

            return $event->load(['user', 'links', 'participants', 'reminders']);
        });
    }

    public function delete(string $tenantId, string $id): void
    {
        $event = $this->find($tenantId, $id);
        $event->delete();
    }

    public function updateStatus(string $tenantId, string $id, string $status): CRMEvent
    {
        $event = $this->find($tenantId, $id);
        $event->status = $status;
        $event->save();

        return $event;
    }

    public function upcoming(string $tenantId, string $userId, int $limit = 10): Collection
    {
        /** @var Collection<int, CRMEvent> */
        return CRMEvent::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($userId): void {
                $query->where('auth_user_id', $userId)
                    ->orWhereHas('participants', fn ($participant) => $participant->where('auth_user_id', $userId));
            })
            ->where('starts_at', '>=', now())
            ->where('status', CRMEvent::STATUS_SCHEDULED)
            ->orderBy('starts_at')
            ->limit($limit)
            ->with(['links.linkable'])
            ->get();
    }

    public function linked(string $tenantId, string $linkableAlias, string $linkableId): Collection
    {
        $morphType = CRMEventLink::resolveLinkableType($linkableAlias);

        if ($morphType === null) {
            return new Collection;
        }

        /** @var Collection<int, CRMEvent> */
        return CRMEvent::query()
            ->where('tenant_id', $tenantId)
            ->whereHas('links', function ($query) use ($morphType, $linkableId): void {
                $query->where('linkable_type', $morphType)->where('linkable_id', $linkableId);
            })
            ->with(['user', 'participants'])
            ->orderBy('starts_at')
            ->get();
    }

    public function statistics(string $tenantId, ?CarbonImmutable $startDate = null, ?CarbonImmutable $endDate = null): array
    {
        $base = CRMEvent::query()->where('tenant_id', $tenantId);

        $applyRange = function ($query) use ($startDate, $endDate): void {
            if ($startDate !== null) {
                $query->where('starts_at', '>=', $startDate);
            }
            if ($endDate !== null) {
                $query->where('starts_at', '<=', $endDate);
            }
        };

        $totalQuery = clone $base;
        $applyRange($totalQuery);

        $byStatusQuery = clone $base;
        $applyRange($byStatusQuery);
        $byStatus = $byStatusQuery
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        $byTypeQuery = clone $base;
        $applyRange($byTypeQuery);
        $byType = $byTypeQuery
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $upcomingQuery = clone $base;
        $applyRange($upcomingQuery);
        $upcoming = $upcomingQuery
            ->where('starts_at', '>=', now())
            ->where('status', CRMEvent::STATUS_SCHEDULED)
            ->count();

        return [
            'total' => $totalQuery->count(),
            'by_status' => $byStatus,
            'by_type' => $byType,
            'upcoming' => $upcoming,
        ];
    }

    /**
     * @param  array<int, array{type: string, id: string}>  $links
     */
    private function syncLinks(CRMEvent $event, array $links): void
    {
        $event->links()->delete();

        foreach ($links as $link) {
            $morphType = CRMEventLink::resolveLinkableType($link['type']);
            if ($morphType === null) {
                continue;
            }

            $event->links()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $event->tenant_id,
                'linkable_type' => $morphType,
                'linkable_id' => $link['id'],
            ]);
        }
    }

    /**
     * @param  array<int, array{auth_user_id?: string|null, crm_contact_id?: string|null, is_organizer?: bool, name?: string|null, email?: string|null}>  $participants
     */
    private function syncParticipants(CRMEvent $event, array $participants): void
    {
        $event->participants()->delete();

        foreach ($participants as $participant) {
            $event->participants()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $event->tenant_id,
                'auth_user_id' => $participant['auth_user_id'] ?? null,
                'crm_contact_id' => $participant['crm_contact_id'] ?? null,
                'name' => $participant['name'] ?? null,
                'email' => $participant['email'] ?? null,
                'status' => CRMEventParticipant::STATUS_PENDING,
                'is_organizer' => $participant['is_organizer'] ?? false,
            ]);
        }
    }

    /**
     * @param  array<int, array{minutes_before: int, notify_ui?: bool, notify_email?: bool, notify_push?: bool, notify_whatsapp?: bool, notify_webhook?: bool}>  $reminders
     */
    private function syncReminders(CRMEvent $event, array $reminders): void
    {
        $event->reminders()->delete();

        foreach ($reminders as $reminder) {
            $startsAt = Carbon::parse($event->starts_at);
            $scheduledAt = $startsAt->copy()->subMinutes($reminder['minutes_before']);

            $event->reminders()->create([
                'id' => (string) Str::orderedUuid(),
                'tenant_id' => $event->tenant_id,
                'auth_user_id' => $event->auth_user_id,
                'type' => 'notification',
                'minutes_before' => $reminder['minutes_before'],
                'notify_ui' => $reminder['notify_ui'] ?? true,
                'notify_email' => $reminder['notify_email'] ?? false,
                'notify_push' => $reminder['notify_push'] ?? false,
                'notify_whatsapp' => $reminder['notify_whatsapp'] ?? false,
                'notify_webhook' => $reminder['notify_webhook'] ?? false,
                'scheduled_at' => $scheduledAt,
                'is_sent' => false,
            ]);
        }
    }
}
