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

    /**
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $invoiceId  Identificador da fatura vencida.
     * @param  float  $amount  Valor em atraso.
     * @param  string  $referenceMonth  Mês de referência no formato YYYY-MM.
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $invoiceId,
        public readonly float $amount,
        public readonly string $referenceMonth,
    ) {}
}
