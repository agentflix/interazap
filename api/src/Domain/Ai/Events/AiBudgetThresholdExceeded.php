<?php

declare(strict_types=1);

namespace Domain\Ai\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Event dispatched when a budget threshold is exceeded.
 *
 * Phase 4: Token Budget System
 */
final class AiBudgetThresholdExceeded
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  string  $tenantId  Tenant UUID
     * @param  string  $period  Period type ('daily' or 'monthly')
     * @param  float  $usage  Current usage in dollars
     * @param  float  $limit  Budget limit in dollars
     * @param  float  $ratio  Usage ratio (0.0-1.0+)
     * @param  string  $level  Alert level ('warning' or 'critical')
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $period,
        public readonly float $usage,
        public readonly float $limit,
        public readonly float $ratio,
        public readonly string $level
    ) {}
}
