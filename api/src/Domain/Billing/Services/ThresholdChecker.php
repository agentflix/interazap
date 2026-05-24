<?php

declare(strict_types=1);

namespace Domain\Billing\Services;

use Domain\Billing\Models\TenantMessageUsage;
use Illuminate\Support\Carbon;

/**
 * Checks message usage thresholds and marks alerts as sent.
 *
 * Returns the list of thresholds (80, 100) that should trigger alerts,
 * marking them as sent on the usage row to prevent duplicate notifications.
 */
final class ThresholdChecker
{
    /**
     * Returns thresholds that should trigger alerts (not yet sent).
     *
     * Modifies the $usage model in-place (saves to DB if any threshold fires).
     *
     * @return list<int> e.g. [80], [100], [80, 100], []
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
