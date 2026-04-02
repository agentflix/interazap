<?php

declare(strict_types=1);

namespace Domain\Billing\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento para notificação de cobrança enviada.
 */
final class BillingCollectionSentEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $invoiceId,
        public readonly string $templateId,
        public readonly string $channel,
        public readonly string $recipient,
        public readonly string $status,
    ) {}
}
