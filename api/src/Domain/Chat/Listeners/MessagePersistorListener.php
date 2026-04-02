<?php

declare(strict_types=1);

namespace Domain\Chat\Listeners;

use Domain\Ai\Services\AiConversationSummaryService;
use Domain\Chat\Events\MessagePersisted;
use Domain\Chat\Jobs\ConversationResolverJob;

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

        ConversationResolverJob::dispatch(
            tenantId: $event->tenantId,
            ticketId: $event->ticketId,
            body: $event->body,
            context: $event->context,
        );
    }
}
