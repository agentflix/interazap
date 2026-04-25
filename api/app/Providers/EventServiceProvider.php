<?php

declare(strict_types=1);

namespace App\Providers;

use Domain\Ai\Events\AiBudgetThresholdExceeded;
use Domain\Ai\Events\AiResponseReceived;
use Domain\Ai\Events\AiRunCompleted;
use Domain\Ai\Events\AiRunRequested;
use Domain\Ai\Events\AutopilotTriggerFired;
use Domain\Ai\Listeners\AiBudgetThresholdNotificationListener;
use Domain\Ai\Listeners\AiConversationSummaryListener;
use Domain\Ai\Listeners\AiGateKeeperListener;
use Domain\Ai\Listeners\AutopilotRunDispatcherListener;
use Domain\Chat\Events\MessagePersisted;
use Domain\Chat\Listeners\AiResponseListener;
use Domain\Chat\Listeners\MessagePersistorListener;
use Domain\Configuration\Events\AiEscalationRequiredEvent;
use Domain\Configuration\Events\AiHotLeadDetectedEvent;
use Domain\Configuration\Events\BillingInvoiceCreatedEvent;
use Domain\Configuration\Events\BillingPaymentConfirmedEvent;
use Domain\Configuration\Events\BillingPaymentOverdueEvent;
use Domain\Configuration\Events\EvaluationLowScoreEvent;
use Domain\Configuration\Events\NegotiationLostEvent;
use Domain\Configuration\Events\NegotiationWonEvent;
use Domain\Configuration\Events\StorageLimitWarningEvent;
use Domain\Configuration\Events\TicketAssignedEvent;
use Domain\Configuration\Events\TicketClosedEvent;
use Domain\Configuration\Events\TicketCreatedEvent;
use Domain\Configuration\Listeners\AiNotificationListener;
use Domain\Configuration\Listeners\BillingNotificationListener;
use Domain\Configuration\Listeners\ChatTicketNotificationListener;
use Domain\Configuration\Listeners\CrmNotificationListener;
use Domain\Configuration\Listeners\EvaluationNotificationListener;
use Domain\Platform\Console\Listeners\WorkerGracefulShutdownListener;
use Domain\Shared\Listeners\AuthEventSubscriber;
use Domain\Shared\Listeners\SecurityEventSubscriber;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Queue\Events\Looping;
use Illuminate\Queue\Events\WorkerStopping;
use Illuminate\Support\Facades\Event;

/**
 * Registra event listeners e subscribers da aplicação.
 *
 * @category Providers
 */
class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        TicketCreatedEvent::class => [
            ChatTicketNotificationListener::class,
        ],
        TicketAssignedEvent::class => [
            ChatTicketNotificationListener::class,
        ],
        TicketClosedEvent::class => [
            ChatTicketNotificationListener::class,
        ],
        BillingPaymentConfirmedEvent::class => [
            BillingNotificationListener::class,
        ],
        BillingPaymentOverdueEvent::class => [
            BillingNotificationListener::class,
        ],
        BillingInvoiceCreatedEvent::class => [
            BillingNotificationListener::class,
        ],
        NegotiationWonEvent::class => [
            CrmNotificationListener::class,
        ],
        NegotiationLostEvent::class => [
            CrmNotificationListener::class,
        ],
        AiHotLeadDetectedEvent::class => [
            AiNotificationListener::class,
        ],
        AiEscalationRequiredEvent::class => [
            AiNotificationListener::class,
        ],
        StorageLimitWarningEvent::class => [
            AiNotificationListener::class,
        ],
        EvaluationLowScoreEvent::class => [
            EvaluationNotificationListener::class,
        ],
        MessagePersisted::class => [
            MessagePersistorListener::class,
        ],
        AiRunRequested::class => [
            AiGateKeeperListener::class,
        ],
        AiRunCompleted::class => [
            AiConversationSummaryListener::class,
        ],
        AiBudgetThresholdExceeded::class => [
            AiBudgetThresholdNotificationListener::class,
        ],
        AutopilotTriggerFired::class => [
            AutopilotRunDispatcherListener::class,
        ],
        AiResponseReceived::class => [
            AiResponseListener::class,
        ],
    ];

    /**
     * The subscriber classes to register.
     *
     * @var array<int, class-string>
     */
    protected $subscribe = [
        AuthEventSubscriber::class,
        SecurityEventSubscriber::class,
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        parent::boot();

        // Queue worker graceful shutdown listeners
        Event::listen(Looping::class, [WorkerGracefulShutdownListener::class, 'handleLooping']);
        Event::listen(JobProcessing::class, [WorkerGracefulShutdownListener::class, 'handleJobProcessing']);
        Event::listen(JobProcessed::class, [WorkerGracefulShutdownListener::class, 'handleJobProcessed']);
        Event::listen(WorkerStopping::class, [WorkerGracefulShutdownListener::class, 'handleWorkerStopping']);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}
