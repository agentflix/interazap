<?php

declare(strict_types=1);

namespace Domain\Billing\Console\Commands;

use Domain\Billing\Jobs\CloseExpiredTrialJob;
use Illuminate\Console\Command;

/**
 * Artisan command que dispara o job `CloseExpiredTrialJob`.
 *
 * Signature: `billing:close-expired-trials`
 * Schedule: diário às 03:00 UTC (registrado em `routes/console.php`)
 */
final class CloseExpiredTrialsCommand extends Command
{
    protected $signature = 'billing:close-expired-trials';

    protected $description = 'Identifica tenants com trial expirado e envia email de encerramento.';

    public function handle(): int
    {
        $this->info('[CloseExpiredTrials] Dispatching job...');

        CloseExpiredTrialJob::dispatch();

        $this->info('[CloseExpiredTrials] Job dispatched successfully.');

        return self::SUCCESS;
    }
}
