<?php

declare(strict_types=1);

namespace Domain\Ai\Listeners;

use Domain\Ai\Events\AiRunCompleted;
use Domain\Ai\Jobs\AiSummarizeConversationJob;

/**
 * Listener for AI run completion events that dispatches conversation summarization.
 */
final class AiConversationSummaryListener
{
    /**
     * Handle the event.
     */
    public function handle(AiRunCompleted $event): void
    {
        AiSummarizeConversationJob::dispatch($event->tenantId, $event->ticketId);
    }
}
