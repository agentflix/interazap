<?php

declare(strict_types=1);

namespace Domain\Configuration\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de domínio para lead quente detectado por IA.
 */
final class AiHotLeadDetectedEvent
{
    use Dispatchable;
    use SerializesModels;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $title,
        public readonly string $message,
        public readonly array $data = [],
    ) {}
}
