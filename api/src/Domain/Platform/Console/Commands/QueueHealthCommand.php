<?php

declare(strict_types=1);

namespace Domain\Platform\Console\Commands;

use Domain\Platform\Services\QueueHealthService;
use Illuminate\Console\Command;

/**
 * Comando para verificar o status de saúde das filas.
 *
 * Útil para monitoramento, alertas e diagnóstico de problemas em filas.
 */
final class QueueHealthCommand extends Command
{
    /**
     * Assinatura e opções do comando.
     *
     * @var string
     */
    protected $signature = 'queue:health
                            {--queue= : Check specific queue only}
                            {--json : Output in JSON format}';

    /**
     * Descrição do comando exibida no artisan help.
     *
     * @var string
     */
    protected $description = 'Verifica o status de saúde das filas';

    /**
     * Executa o comando de verificação de saúde das filas.
     *
     * @param  QueueHealthService  $healthService  Serviço de monitoramento de filas.
     * @return int Código de saída: 0 = saudável, 1 = problemas detectados.
     */
    public function handle(QueueHealthService $healthService): int
    {
        $queueFilter = $this->option('queue');
        $jsonOutput = $this->option('json');

        if ($queueFilter) {
            $healthService->setQueues([$queueFilter]);
        }

        $status = $healthService->getHealthStatus();

        if ($jsonOutput) {
            $this->line(json_encode($status, JSON_PRETTY_PRINT));

            return $status['healthy'] ? self::SUCCESS : self::FAILURE;
        }

        $this->displayStatus($status);

        return $status['healthy'] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Exibe o status de saúde das filas em formato legível no console.
     *
     * @param  array<string, mixed>  $status  Dados de saúde retornados pelo serviço.
     */
    protected function displayStatus(array $status): void
    {
        $this->newLine();

        if ($status['healthy']) {
            $this->info('✓ Queue system is healthy');
        } else {
            $this->error('✗ Queue system has issues');
        }

        $this->newLine();

        // Display issues
        if (! empty($status['issues'])) {
            $this->warn('Issues:');
            foreach ($status['issues'] as $issue) {
                $this->line("  • {$issue}");
            }
            $this->newLine();
        }

        // Display queue stats
        $this->info('Queue Statistics:');
        $this->table(
            ['Queue', 'Size', 'Delayed'],
            array_map(
                fn ($q) => [$q['name'], $q['size'], $q['delayed']],
                $status['queues']
            )
        );

        $this->newLine();

        // Display summary
        $this->line("Workers: {$status['workers']}");
        $this->line("Stuck Jobs: {$status['stuck_jobs']}");
        $this->line("Checked At: {$status['checked_at']}");
    }
}
