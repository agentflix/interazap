<?php

declare(strict_types=1);

namespace Domain\Billing\Console\Commands;

use Domain\Billing\Actions\BillingSendRemindersAction;
use Illuminate\Console\Command;

/**
 * Comando para envio de lembretes de cobrança.
 */
final class BillingSendRemindersCommand extends Command
{
    protected $signature = 'billing:send-reminders {--dry-run : Simula sem enviar notificações}';

    protected $description = 'Envia lembretes de cobrança para tenants inadimplentes.';

    public function __construct(
        private readonly BillingSendRemindersAction $action,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $this->action->handle($dryRun);

        $this->info('Billing reminders finalizado.');
        $this->table(['processed', 'sent', 'skipped', 'failed', 'dry_run'], [[
            $result['processed'],
            $result['sent'],
            $result['skipped'],
            $result['failed'],
            $dryRun ? 'yes' : 'no',
        ]]);

        return self::SUCCESS;
    }
}
