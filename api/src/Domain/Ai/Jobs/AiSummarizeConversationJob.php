<?php

declare(strict_types=1);

namespace Domain\Ai\Jobs;

use Domain\Ai\Services\AiConversationSummaryService;
use Domain\Shared\Concerns\HasJobDefaults;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Job for summarizing AI conversations.
 *
 * @category Jobs
 */
final class AiSummarizeConversationJob implements ShouldQueue
{
    use Dispatchable;
    use HasJobDefaults;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        private readonly string $tenantId,
        private readonly string $ticketId,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(AiConversationSummaryService $service): void
    {
        $service->summarize($this->tenantId, $this->ticketId);
    }
}
