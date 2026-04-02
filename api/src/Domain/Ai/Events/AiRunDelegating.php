<?php

declare(strict_types=1);

namespace Domain\Ai\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event emitted before a parent run delegates execution to another agent.
 */
final class AiRunDelegating
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $tenantId,
        public string $parentRunId,
        public string $sourceAgentId,
        public string $targetAgentId,
        public array $payload = [],
    ) {}
}
