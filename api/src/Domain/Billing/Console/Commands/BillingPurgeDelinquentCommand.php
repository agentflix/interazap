<?php

declare(strict_types=1);

namespace Domain\Billing\Console\Commands;

use Domain\Billing\Actions\BillingPurgeTenantAction;
use Domain\Billing\Enums\BillingTenantStatus;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Console\Command;

/**
 * Comando para purge de tenants em estado pending_purge elegíveis.
 */
final class BillingPurgeDelinquentCommand extends Command
{
    protected $signature = 'billing:purge-delinquent {--dry-run : Simula purge sem deletar dados}';

    protected $description = 'Executa purge de tenants inadimplentes elegíveis.';

    public function __construct(
        private readonly BillingPurgeTenantAction $purgeTenantAction,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $tenants = PlatformTenant::query()
            ->withoutGlobalScopes()
            ->where('billing_status', BillingTenantStatus::PENDING_PURGE->value)
            ->whereDate('billing_purge_deadline', '<=', today())
            ->get();

        $processed = 0;
        $purged = 0;

        foreach ($tenants as $tenant) {
            $processed++;
            $result = $this->purgeTenantAction->handle($tenant, $dryRun);
            if ($result['purged'] === true) {
                $purged++;
            }
        }

        $this->info('Billing purge finalizado.');
        $this->table(['processed', 'purged', 'dry_run'], [[
            $processed,
            $purged,
            $dryRun ? 'yes' : 'no',
        ]]);

        return self::SUCCESS;
    }
}
