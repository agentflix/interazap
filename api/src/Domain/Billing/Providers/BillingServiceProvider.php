<?php

declare(strict_types=1);

namespace Domain\Billing\Providers;

use Domain\Billing\Console\Commands\BillingCheckOverdueCommand;
use Domain\Billing\Console\Commands\BillingGenerateMonthlyInvoicesCommand;
use Domain\Billing\Console\Commands\BillingPurgeDelinquentCommand;
use Domain\Billing\Console\Commands\BillingSendRemindersCommand;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider do domínio Billing.
 */
final class BillingServiceProvider extends ServiceProvider
{
    /**
     * Registra comandos de billing.
     */
    public function register(): void
    {
        $this->commands([
            BillingCheckOverdueCommand::class,
            BillingGenerateMonthlyInvoicesCommand::class,
            BillingSendRemindersCommand::class,
            BillingPurgeDelinquentCommand::class,
        ]);
    }

    /**
     * Registra agendamentos de delinquency.
     */
    public function boot(): void
    {
        $this->app->booted(function (): void {
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            $schedule->command('billing:check-overdue')
                ->hourly()
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command('billing:generate-monthly-invoices')
                ->monthlyOn(1, '06:00')
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command('billing:send-reminders')
                ->daily()
                ->at('09:00')
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command('billing:purge-delinquent')
                ->daily()
                ->at('02:00')
                ->withoutOverlapping()
                ->onOneServer();
        });
    }
}
