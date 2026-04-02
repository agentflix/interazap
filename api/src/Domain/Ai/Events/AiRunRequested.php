<?php

declare(strict_types=1);

namespace Domain\Ai\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class AiRunRequested
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $ticketId,
        public readonly string $body,
        public readonly array $context = [],
    ) {}
}
