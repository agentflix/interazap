<?php

declare(strict_types=1);

namespace Domain\Billing\Services;

use Carbon\CarbonImmutable;

/**
 * Calculates billing cycle boundaries based on an anchor day.
 */
final class BillingCycleCalculator
{
    /**
     * Calculate cycle boundaries for a given anchor day and reference date.
     *
     * The anchor day is capped at 28 to avoid February overflow issues.
     *
     * @param  int  $anchorDay  Day of month for cycle start (1-31, capped at 28)
     * @param  CarbonImmutable  $reference  Reference date for calculation
     * @return array{cycle_start: CarbonImmutable, cycle_end: CarbonImmutable}
     */
    public function calculate(int $anchorDay, CarbonImmutable $reference): array
    {
        // Cap anchor to max 28 to avoid Feb issues
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
