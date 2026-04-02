<?php

declare(strict_types=1);

namespace Domain\Ai\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event emitted after a child run is created by delegation.
 */
final class AiRunDelegated
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $tenantId,
        public string $parentRunId,
        public string $childRunId,
        public string $sourceAgentId,
        public string $targetAgentId,
        public array $payload = [],
    ) {}
}
