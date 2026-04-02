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
 * Shared Domain Service Provider.
 *
 * Registra serviços compartilhados, comandos e schedules do módulo Shared.
 *
 * @category Providers
 */
final class SharedServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(PrometheusRegistry::class);

        $this->app->singleton(CollectorRegistry::class, static function (Container $app): CollectorRegistry {
            return $app->make(PrometheusRegistry::class)->get();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->scheduleAuditCleanup();
    }

    /**
     * Schedule the audit logs cleanup job.
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
