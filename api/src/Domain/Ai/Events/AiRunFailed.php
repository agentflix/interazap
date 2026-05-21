<?php

declare(strict_types=1);

namespace Domain\Ai\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AiRunFailed
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $ticketId,
        public readonly string $runId,
        public readonly string $correlationId,
        public readonly string $error,
        public readonly string $errorCode = 'run_failed',
    ) {}
}
