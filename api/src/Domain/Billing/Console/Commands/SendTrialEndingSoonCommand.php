<?php

declare(strict_types=1);

namespace Domain\Billing\Console\Commands;

use Domain\Billing\Jobs\SendTrialEndingSoonJob;
use Illuminate\Console\Command;

/**
 * Artisan command que dispara o job `SendTrialEndingSoonJob`.
 *
 * Signature: `billing:send-trial-ending-soon`
 * Schedule: diário às 09:00 UTC (registrado em `routes/console.php`)
 */
final class SendTrialEndingSoonCommand extends Command
{
    protected $signature = 'billing:send-trial-ending-soon';

    protected $description = 'Envia lembrete para tenants com trial terminando em ≤2 dias.';

    public function handle(): int
    {
        $this->info('[SendTrialEndingSoon] Dispatching job...');

        SendTrialEndingSoonJob::dispatch();

        $this->info('[SendTrialEndingSoon] Job dispatched successfully.');

        return self::SUCCESS;
    }
}
