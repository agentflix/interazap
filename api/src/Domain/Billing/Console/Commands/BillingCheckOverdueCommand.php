<?php

declare(strict_types=1);

namespace Domain\Billing\Console\Commands;

use Domain\Billing\Actions\BillingCheckOverdueAction;
use Illuminate\Console\Command;

/**
 * Comando para verificar e atualizar status de inadimplência.
 */
final class BillingCheckOverdueCommand extends Command
{
    protected $signature = 'billing:check-overdue {--dry-run : Simula sem persistir alterações}';

    protected $description = 'Verifica faturas em atraso e atualiza status de inadimplência dos tenants.';

    public function __construct(
        private readonly BillingCheckOverdueAction $action,
    ) {
        parent::__construct();
    }

    /**
     * Executa o comando de verificação de inadimplência.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $this->action->handle($dryRun);

        $this->info('Billing overdue check finalizado.');
        $this->table(['processed', 'grace', 'locked', 'pending_purge', 'dry_run'], [[
            $result['processed'],
            $result['grace'],
            $result['locked'],
            $result['pending_purge'],
            $dryRun ? 'yes' : 'no',
        ]]);

        return self::SUCCESS;
    }
}
