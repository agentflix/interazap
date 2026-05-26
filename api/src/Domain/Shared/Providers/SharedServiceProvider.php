<?php

declare(strict_types=1);

namespace Domain\Shared\Providers;

use Domain\Shared\Jobs\CleanupAuditLogsJob;
use Domain\Shared\Services\PrometheusRegistry;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;
use Prometheus\CollectorRegistry;

/**
 * Service Provider do domínio Shared.
 *
 * Registra singletons do PrometheusRegistry/CollectorRegistry e agenda
 * o job de limpeza de audit logs diariamente às 02:00 (sem sobreposição).
 */
final class SharedServiceProvider extends ServiceProvider
{
    /**
     * Registra os serviços compartilhados no container da aplicação.
     */
    public function register(): void
    {
        $this->app->singleton(PrometheusRegistry::class);

        $this->app->singleton(CollectorRegistry::class, static function (Container $app): CollectorRegistry {
            return $app->make(PrometheusRegistry::class)->get();
        });
    }

    /**
     * Inicializa os serviços após o boot da aplicação.
     */
    public function boot(): void
    {
        $this->scheduleAuditCleanup();
    }

    /**
     * Agenda o job de limpeza de logs de auditoria para execução diária às 02:00.
     */
    private function scheduleAuditCleanup(): void
    {
        $this->app->booted(function (): void {
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            // Run cleanup job daily at 2 AM
            $schedule->job(new CleanupAuditLogsJob)
                ->daily()
                ->at('02:00')
                ->withoutOverlapping()
                ->onOneServer()
                ->name('cleanup-audit-logs');
        });
    }
}
