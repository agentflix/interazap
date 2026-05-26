<?php

declare(strict_types=1);

namespace Domain\Platform\Console\Commands;

use Domain\Platform\Actions\PlatformTenantBootstrapAction;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Console\Command;

/**
 * Reaplica o bootstrap de defaults para um tenant específico.
 */
final class TenantBootstrapDefaultsCommand extends Command
{
    protected $signature = 'tenant:bootstrap-defaults
                            {tenantId : UUID do tenant}
                            {--dry-run : Exibe o relatório sem persistir alterações}';

    protected $description = 'Rebootstrap de defaults de AI e CRM para um tenant';

    /**
     * Executa o rebootstrap de defaults para o tenant informado.
     *
     * @param  PlatformTenantBootstrapAction  $bootstrapAction  Ação de bootstrap do tenant.
     * @return int Código de saída: 0 = sucesso, 1 = falha.
     */
    public function handle(PlatformTenantBootstrapAction $bootstrapAction): int
    {
        $tenantId = (string) $this->argument('tenantId');
        $dryRun = (bool) $this->option('dry-run');

        $tenant = PlatformTenant::query()->find($tenantId);

        if (! $tenant instanceof PlatformTenant) {
            $this->error('Tenant não encontrado.');

            return self::FAILURE;
        }

        $report = $bootstrapAction->execute($tenant, $dryRun);

        $this->info($dryRun ? 'Dry-run concluído com sucesso.' : 'Bootstrap concluído com sucesso.');

        $this->table(
            ['Campo', 'Valor'],
            [
                ['tenant_id', (string) $report['tenant_id']],
                ['segment_id', (string) $report['segment_id']],
                ['segment_code', (string) $report['segment_code']],
                ['prompt', (string) $report['prompt']],
                ['playbooks', (string) $report['playbooks']],
                ['tools', (string) $report['tools']],
                ['guardrails', (string) $report['guardrails']],
                ['funnels', (string) $report['funnels']],
                ['funnel_steps', (string) $report['funnel_steps']],
                ['loss_reasons', (string) $report['loss_reasons']],
                ['tags', (string) $report['tags']],
                ['departments', (string) $report['departments']],
                ['dry_run', (string) $report['dry_run']],
            ]
        );

        return self::SUCCESS;
    }
}
