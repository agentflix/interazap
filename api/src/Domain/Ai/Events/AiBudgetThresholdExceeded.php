<?php

declare(strict_types=1);

namespace Domain\Ai\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Evento disparado quando o consumo do tenant ultrapassa um limiar de orçamento.
 *
 * Emitido pelo sistema de monitoramento de budget (nível warning ou critical)
 * para notificar administradores via AiBudgetThresholdNotificationListener.
 */
final class AiBudgetThresholdExceeded
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    /**
     * @param  string  $tenantId  UUID do tenant.
     * @param  string  $period  Período de apuração ('daily' ou 'monthly').
     * @param  float  $usage  Uso atual em dólares.
     * @param  float  $limit  Limite de orçamento em dólares.
     * @param  float  $ratio  Razão de uso (0.0–1.0+); valores acima de 1.0 indicam estouro.
     * @param  string  $level  Nível do alerta ('warning' ou 'critical').
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
