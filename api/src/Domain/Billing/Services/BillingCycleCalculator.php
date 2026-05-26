<?php

declare(strict_types=1);

namespace Domain\Billing\Services;

use Carbon\CarbonImmutable;

/**
 * Calcula os limites do ciclo de billing com base em um dia âncora mensal ou duração fixa.
 *
 * Planos regulares usam âncora mensal (1–28); planos trial usam duração fixa em dias.
 */
final class BillingCycleCalculator
{
    /**
     * Calcula o início e fim do ciclo para um dia âncora e data de referência.
     *
     * O dia âncora é limitado a 28 para evitar estouro em fevereiro.
     *
     * Quando `$cycleDays` é fornecido, o ciclo é calculado como uma janela de
     * duração fixa a partir da data de referência (modo trial). Quando nulo,
     * usa o comportamento padrão de âncora mensal.
     *
     * @param  int  $anchorDay  Dia do mês para início do ciclo (1-31, limitado a 28); ignorado quando $cycleDays está definido
     * @param  CarbonImmutable  $reference  Data de referência para o cálculo
     * @param  int|null  $cycleDays  Duração fixa do ciclo em dias (ex: 7 para trial). Nulo = modo âncora mensal.
     * @return array{cycle_start: CarbonImmutable, cycle_end: CarbonImmutable}
     */
    public function calculate(int $anchorDay, CarbonImmutable $reference, ?int $cycleDays = null): array
    {
        // Fixed-duration mode: trial plans use cycle_days instead of monthly anchor
        if ($cycleDays !== null) {
            $cycleStart = $reference->startOfDay();
            $cycleEnd = $cycleStart->addDays($cycleDays)->subSecond();

            return [
                'cycle_start' => $cycleStart,
                'cycle_end' => $cycleEnd,
            ];
        }

        // Monthly anchor mode (legacy behavior): cap anchor to max 28 to avoid Feb issues
        $anchor = min($anchorDay, 28);

        // Determine cycle_start: same month if reference.day >= anchor, else previous month
        if ($reference->day >= $anchor) {
            $cycleStart = $reference->startOfMonth()->addDays($anchor - 1);
        } else {
            $cycleStart = $reference->subMonthNoOverflow()->startOfMonth()->addDays($anchor - 1);
        }

        // cycle_end = day before next anchor
        $cycleEnd = $cycleStart->addMonthNoOverflow()->subDay();

        return [
            'cycle_start' => $cycleStart,
            'cycle_end' => $cycleEnd,
        ];
    }
}
