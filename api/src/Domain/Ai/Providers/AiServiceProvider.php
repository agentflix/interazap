<?php

declare(strict_types=1);

namespace Domain\Ai\Providers;

use Domain\Ai\Console\Commands\ConsumeAiRunResponsesCommand;
use Domain\Ai\Console\Commands\ConsumeToolRequestsCommand;
use Domain\Ai\Console\Commands\DetectStaleRunsCommand;
use Domain\Ai\Console\Commands\GenerateDailySummariesCommand;
use Domain\Ai\Console\Commands\PurgeAiUsageLogsCommand;
use Domain\Ai\Console\Commands\ReindexAllKnowledgeDocumentsCommand;
use Domain\Ai\Console\Commands\RunScheduledTriggersCommand;
use Domain\Ai\Models\AiAgentTrigger;
use Domain\Ai\Observers\AiAgentTriggerObserver;
use Domain\Ai\Observers\TenantPromptObserver;
use Domain\Ai\Services\AiContextBuilderService;
use Domain\Ai\Services\AiConversationSummaryService;
use Domain\Ai\Services\AiPromptResolverService;
use Domain\Ai\Services\AiSummarizationService;
use Domain\Ai\Services\ContextWindowManagerService;
use Domain\Ai\Services\GuardrailEvaluatorService;
use Domain\Ai\Services\ToolDispatcherService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\ServiceProvider;

/**
 * AI Domain Service Provider.
 *
 * Registra comandos, serviços e schedules do módulo AI.
 */
class AiServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register commands for console
        $this->commands([
            PurgeAiUsageLogsCommand::class,
            ReindexAllKnowledgeDocumentsCommand::class,
            RunScheduledTriggersCommand::class,
            GenerateDailySummariesCommand::class,
            ConsumeAiRunResponsesCommand::class,
            ConsumeToolRequestsCommand::class,
            DetectStaleRunsCommand::class,
        ]);

        $this->app->singleton(ToolDispatcherService::class);
        $this->app->singleton(GuardrailEvaluatorService::class);
        $this->app->singleton(AiPromptResolverService::class);
        $this->app->singleton(AiContextBuilderService::class);
        $this->app->singleton(ContextWindowManagerService::class);
        $this->app->singleton(AiSummarizationService::class);
        $this->app->singleton(AiConversationSummaryService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        AiAgentTrigger::observe(AiAgentTriggerObserver::class);
        PlatformTenant::observe(TenantPromptObserver::class);

        // Schedule LGPD purge job to run daily at 3 AM
        $this->app->booted(function (): void {
            /** @var Schedule $schedule */
            $schedule = $this->app->make(Schedule::class);

            $schedule->command('ai:purge-usage-logs')
                ->daily()
                ->at('03:00')
                ->runInBackground()
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command('ai:run-scheduled-triggers')
                ->everyMinute()
                ->runInBackground()
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command('ai:generate-daily-summaries')
                ->daily()
                ->at('02:00')
                ->runInBackground()
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command('ai:consume-run-responses --once')
                ->everyMinute()
                ->runInBackground()
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command('ai:consume-tool-requests --once')
                ->everyMinute()
                ->runInBackground()
                ->withoutOverlapping()
                ->onOneServer();

            $schedule->command('ai:detect-stale-runs')
                ->everyFiveMinutes()
                ->runInBackground()
                ->withoutOverlapping()
                ->onOneServer();
        });
    }
}
