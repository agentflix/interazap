<?php

declare(strict_types=1);

namespace Domain\Configuration\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento de domínio para fatura vencida.
 */
final class BillingPaymentOverdueEvent
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $invoiceId,
        public readonly float $amount,
        public readonly string $referenceMonth,
    ) {}
}
