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
use Domain\Ai\Contracts\AiAgentToolPermissionServiceInterface;
use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAgentTrigger;
use Domain\Ai\Models\AiAutopilotGuardrail;
use Domain\Ai\Observers\AiAgentObserver;
use Domain\Ai\Observers\AiAgentTriggerObserver;
use Domain\Ai\Observers\TenantPromptObserver;
use Domain\Ai\Services\AiAgentToolPermissionService;
use Domain\Ai\Services\AiContextBuilderService;
use Domain\Ai\Services\AiConversationSummaryService;
use Domain\Ai\Services\AiPromptResolverService;
use Domain\Ai\Services\AiSummarizationService;
use Domain\Ai\Services\ContextWindowManagerService;
use Domain\Ai\Services\GuardrailEvaluatorService;
use Domain\Ai\Services\ToolDispatcherService;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Observers\MessageObserver;
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
     * Registra comandos, bindings e singletons do módulo de IA no container.
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

        $this->app->bind(AiAgentToolPermissionServiceInterface::class, AiAgentToolPermissionService::class);
        $this->app->singleton(ToolDispatcherService::class);
        $this->app->singleton(GuardrailEvaluatorService::class);
        $this->app->singleton(AiPromptResolverService::class);
        $this->app->singleton(AiContextBuilderService::class);
        $this->app->singleton(ContextWindowManagerService::class);
        $this->app->singleton(AiSummarizationService::class);
        $this->app->singleton(AiConversationSummaryService::class);
    }

    /**
     * Inicializa observers, schedules de comandos e outras integrações do módulo de IA.
     */
    public function boot(): void
    {
        AiAgentTrigger::observe(AiAgentTriggerObserver::class);
        AiAgent::observe(AiAgentObserver::class);
        AiAutopilotGuardrail::observe(\Domain\Ai\Observers\AiAutopilotGuardrailObserver::class);
        ChatMessage::observe(MessageObserver::class);
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

            // Run consumers near-continuously: each tick keeps the worker alive for ~55s,
            // so the next scheduler tick (every minute) seamlessly takes over.
            // In production, Supervisor owns the long-running process; `withoutOverlapping`
            // makes this schedule a harmless watchdog there.
            $schedule->command('ai:consume-run-responses --max-runtime=55')
                ->everyMinute()
                ->runInBackground()
                ->withoutOverlapping(2)
                ->onOneServer();

            $schedule->command('ai:consume-tool-requests --max-runtime=55')
                ->everyMinute()
                ->runInBackground()
                ->withoutOverlapping(2)
                ->onOneServer();

            $schedule->command('ai:detect-stale-runs')
                ->everyMinute()
                ->runInBackground()
                ->withoutOverlapping()
                ->onOneServer();
        });
    }
}
