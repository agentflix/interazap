<?php

declare(strict_types=1);

namespace Domain\Billing\Services;

use Domain\Billing\Models\TenantMessageUsage;
use Illuminate\Support\Carbon;

/**
 * Verifica limiares de uso de mensagens e marca alertas como enviados.
 *
 * Retorna a lista de limiares (80, 100) que devem disparar alertas,
 * marcando-os na linha de uso para evitar notificações duplicadas.
 */
final class ThresholdChecker
{
    /**
     * Retorna os limiares que ainda não foram notificados e devem disparar alertas.
     *
     * Modifica o modelo $usage in-place (salva no DB quando algum limiar é atingido).
     *
     * @param  TenantMessageUsage  $usage  Linha de uso do ciclo atual
     * @param  int  $limit  Limite mensal de mensagens do plano
     * @return list<int> Ex: [80], [100], [80, 100] ou []
     */
    public function check(TenantMessageUsage $usage, int $limit): array
    {
        if ($limit === 0) {
            return [];
        }

        $pct = ($usage->message_count / $limit) * 100;
        $toFire = [];

        if ($pct >= 80.0 && $usage->alert_80_sent_at === null) {
            $usage->alert_80_sent_at = Carbon::now();
            $toFire[] = 80;
        }

        if ($pct >= 100.0 && $usage->alert_100_sent_at === null) {
            $usage->alert_100_sent_at = Carbon::now();
            $toFire[] = 100;
        }

        if (! empty($toFire)) {
            $usage->save();
        }

        return $toFire;
    }
}
