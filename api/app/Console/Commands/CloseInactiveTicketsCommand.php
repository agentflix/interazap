<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Domain\Chat\Actions\CloseInactiveTicketsAction;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Comando agendado para fechar tickets inativos por inatividade.
 *
 * Itera sobre todos os tenants ativos (ou um específico via --tenant)
 * e invoca CloseInactiveTicketsAction para identificar e fechar
 * tickets cuja última atividade excedeu o tempo configurado.
 *
 * Agendamento: everyFiveMinutes()
 */
final class CloseInactiveTicketsCommand extends Command
{
    protected $signature = 'chat:close-inactive-tickets {--tenant= : Tenant ID específico}';

    protected $description = 'Fecha tickets inativos por inatividade baseado nas configurações de auto-close';

    /**
     * Executar o comando.
     */
    public function handle(CloseInactiveTicketsAction $action): int
    {
        $tenantOption = $this->option('tenant');

        if ($tenantOption) {
            $tenant = PlatformTenant::find($tenantOption);

            if (! $tenant) {
                $this->error("Tenant {$tenantOption} não encontrado.");

                return self::FAILURE;
            }

            $result = $action->execute($tenant);
            $this->info("Tenant {$tenant->id}: {$this->formatResult($result)}");

            return self::SUCCESS;
        }

        // Processa todos os tenants ativos
        $tenants = PlatformTenant::query()->where('is_active', true)->get();
        $totalClosed = 0;
        $totalSkipped = 0;
        $errors = 0;

        foreach ($tenants as $tenant) {
            try {
                $result = $action->execute($tenant);
                $closed = count($result['closed_ids']);
                $skipped = count($result['skipped_ids']);
                $totalClosed += $closed;
                $totalSkipped += $skipped;

                if ($closed > 0 || $skipped > 0) {
                    $this->info("Tenant {$tenant->id}: fechados={$closed}, ignorados={$skipped}");
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->error("Erro no tenant {$tenant->id}: {$e->getMessage()}");
                Log::error('[auto-close] Erro no tenant {tenant}', [
                    'tenant' => $tenant->id,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $this->info("Total: fechados={$totalClosed}, ignorados={$totalSkipped}, erros={$errors}");

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Formatar resultado para exibição no console.
     *
     * @param  array{closed_ids: list<string>, skipped_ids: list<string>}  $result
     */
    private function formatResult(array $result): string
    {
        return sprintf(
            'fechados=%d, ignorados=%d',
            count($result['closed_ids']),
            count($result['skipped_ids'])
        );
    }
}
