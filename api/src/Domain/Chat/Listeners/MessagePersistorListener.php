<?php

declare(strict_types=1);

namespace Domain\Chat\Listeners;

use Domain\Auth\Models\AuthUser;
use Domain\Ai\Services\AiConversationSummaryService;
use Domain\Chat\Events\MessagePersisted;
use Domain\Chat\Jobs\ConversationResolverJob;
use Domain\Chat\Notifications\NewMessageNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Notification;

/**
 * Listener for message persistence events that invalidates conversation summaries.
 */
final class MessagePersistorListener
{
    public function __construct(private readonly AiConversationSummaryService $summaryService) {}

    /**
     * Handle the event.
     */
    public function handle(MessagePersisted $event): void
    {
        $this->summaryService->invalidateSummary($event->ticketId);
        $this->dispatchNewMessagePush($event);

        ConversationResolverJob::dispatch(
            tenantId: $event->tenantId,
            ticketId: $event->ticketId,
            body: $event->body,
            context: $event->context,
        );
    }

    private function dispatchNewMessagePush(MessagePersisted $event): void
    {
        $users = AuthUser::query()
            ->where('tenant_id', $event->tenantId)
            ->where('is_active', true)
            ->whereHas('deviceTokens', static function (Builder $query): void {
                $query->whereNull('revoked_at')
                    ->whereIn('platform', ['ios', 'android']);
            })
            ->with(['deviceTokens' => static function (HasMany $query): void {
                $query->whereNull('revoked_at')
                    ->whereIn('platform', ['ios', 'android']);
            }])
            ->get();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send(
            $users,
            new NewMessageNotification(
                tenantId: $event->tenantId,
                ticketId: $event->ticketId,
                body: $event->body,
            )
        );
    }
}
