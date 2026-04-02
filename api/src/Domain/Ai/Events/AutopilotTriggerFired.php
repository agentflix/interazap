<?php

declare(strict_types=1);

namespace Domain\Ai\Events;

use Domain\Ai\Enums\AutopilotTriggerType;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de domínio emitido quando um gatilho do Autopilot é disparado.
 */
final class AutopilotTriggerFired
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly AutopilotTriggerType $triggerType,
        public readonly array $context,
        public readonly string $sourceId,
    ) {}
}
