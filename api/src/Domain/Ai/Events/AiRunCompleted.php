<?php

declare(strict_types=1);

namespace Domain\Ai\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AiRunCompleted
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $output
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $ticketId,
        public readonly string $runId,
        public readonly array $output = [],
    ) {}
}
