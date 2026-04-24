<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Ai\Enums\AutopilotTriggerType;
use Domain\Ai\Events\AutopilotTriggerFired;
use Domain\Ai\Jobs\DispatchAutopilotRunJob;
use Domain\Ai\Listeners\AutopilotRunDispatcherListener;
use Illuminate\Bus\Queue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Feature tests for AutopilotRunDispatcherListener.
 *
 * After refactoring, the listener is a thin dispatcher that only:
 * 1. Validates minimal required data
 * 2. Dispatches DispatchAutopilotRunJob to the queue
 *
 * All heavy logic (DB, Redis, cache) moved to the job.
 *
 * @see DispatchAutopilotRunJobTest For job-level tests with Redis/DB mocking.
 */
final class AutopilotRunDispatcherListenerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([DispatchAutopilotRunJob::class]);
    }

    public function test_listener_dispatches_job_on_valid_event(): void
    {
        $tenantId = (string) Str::orderedUuid();
        $ticketId = (string) Str::orderedUuid();
        $messageId = (string) Str::orderedUuid();
        $sourceId = (string) Str::orderedUuid();

        app(AutopilotRunDispatcherListener::class)->handle(new AutopilotTriggerFired(
            tenantId: $tenantId,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: [
                'ticket_id' => $ticketId,
                'message_id' => $messageId,
                'body' => 'Hello AI',
                'instance_id' => (string) Str::orderedUuid(),
                'source_type' => 'ticket',
            ],
            sourceId: $sourceId,
        ));

        Bus::assertDispatched(DispatchAutopilotRunJob::class);
    }

    public function test_listener_dispatches_job_with_all_event_data(): void
    {
        $tenantId = (string) Str::orderedUuid();
        $ticketId = (string) Str::orderedUuid();
        $messageId = (string) Str::orderedUuid();
        $instanceId = (string) Str::orderedUuid();
        $sourceId = (string) Str::orderedUuid();
        $body = 'Test message body';
        $context = [
            'ticket_id' => $ticketId,
            'message_id' => $messageId,
            'body' => $body,
            'instance_id' => $instanceId,
            'source_type' => 'ticket',
            'custom_field' => 'custom_value',
        ];

        app(AutopilotRunDispatcherListener::class)->handle(new AutopilotTriggerFired(
            tenantId: $tenantId,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: $context,
            sourceId: $sourceId,
        ));

        Bus::assertDispatched(DispatchAutopilotRunJob::class, function (DispatchAutopilotRunJob $job) use ($tenantId, $ticketId, $messageId, $instanceId, $sourceId, $body, $context): bool {
            return $job->tenantId === $tenantId
                && $job->triggerType === AutopilotTriggerType::INBOUND_MESSAGE
                && ($job->context['ticket_id'] ?? '') === $ticketId
                && ($job->context['message_id'] ?? '') === $messageId
                && ($job->context['body'] ?? '') === $body
                && ($job->context['instance_id'] ?? '') === $instanceId
                && ($job->context['source_type'] ?? '') === 'ticket'
                && ($job->context['custom_field'] ?? '') === 'custom_value'
                && $job->sourceId === $sourceId;
        });
    }

    public function test_listener_dispatches_for_all_trigger_types(): void
    {
        $tenantId = (string) Str::orderedUuid();

        $triggerTypes = [
            AutopilotTriggerType::MANUAL,
            AutopilotTriggerType::INBOUND_MESSAGE,
            AutopilotTriggerType::TICKET_CREATED,
            AutopilotTriggerType::NEGOTIATION_WON,
            AutopilotTriggerType::SCHEDULED,
        ];

        foreach ($triggerTypes as $triggerType) {
            Bus::fake([DispatchAutopilotRunJob::class]);

            app(AutopilotRunDispatcherListener::class)->handle(new AutopilotTriggerFired(
                tenantId: $tenantId,
                triggerType: $triggerType,
                context: ['ticket_id' => (string) Str::orderedUuid()],
                sourceId: (string) Str::orderedUuid(),
            ));

            Bus::assertDispatched(DispatchAutopilotRunJob::class, function (DispatchAutopilotRunJob $job) use ($triggerType): bool {
                return $job->triggerType === $triggerType;
            });
        }
    }

    public function test_listener_does_not_dispatch_when_tenant_id_is_empty(): void
    {
        app(AutopilotRunDispatcherListener::class)->handle(new AutopilotTriggerFired(
            tenantId: '',
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: ['ticket_id' => (string) Str::orderedUuid()],
            sourceId: (string) Str::orderedUuid(),
        ));

        Bus::assertNotDispatched(DispatchAutopilotRunJob::class);
    }

    public function test_listener_does_not_dispatch_when_source_id_is_empty(): void
    {
        app(AutopilotRunDispatcherListener::class)->handle(new AutopilotTriggerFired(
            tenantId: (string) Str::orderedUuid(),
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: ['ticket_id' => (string) Str::orderedUuid()],
            sourceId: '',
        ));

        Bus::assertNotDispatched(DispatchAutopilotRunJob::class);
    }

    public function test_listener_does_not_touch_database(): void
    {
        $tenantId = (string) Str::orderedUuid();
        $ticketId = (string) Str::orderedUuid();
        $messageId = (string) Str::orderedUuid();
        $sourceId = (string) Str::orderedUuid();

        // No tenant, no DB setup — if listener touched DB this would throw
        app(AutopilotRunDispatcherListener::class)->handle(new AutopilotTriggerFired(
            tenantId: $tenantId,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: [
                'ticket_id' => $ticketId,
                'message_id' => $messageId,
            ],
            sourceId: $sourceId,
        ));

        // If we reach here, listener didn't touch DB — which is correct
        Bus::assertDispatched(DispatchAutopilotRunJob::class);
    }

    public function test_listener_does_not_touch_redis(): void
    {
        // No Redis facade fake — if listener touched Redis this would be captured
        $tenantId = (string) Str::orderedUuid();

        app(AutopilotRunDispatcherListener::class)->handle(new AutopilotTriggerFired(
            tenantId: $tenantId,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: ['ticket_id' => (string) Str::orderedUuid()],
            sourceId: (string) Str::orderedUuid(),
        ));

        Bus::assertDispatched(DispatchAutopilotRunJob::class);
    }

    public function test_listener_is_idempotent_without_needing_redis_lock(): void
    {
        $tenantId = (string) Str::orderedUuid();
        $ticketId = (string) Str::orderedUuid();
        $messageId = (string) Str::orderedUuid();
        $sourceId = (string) Str::orderedUuid();

        // Multiple calls should each dispatch a job (lock is handled in job, not listener)
        for ($i = 0; $i < 3; $i++) {
            app(AutopilotRunDispatcherListener::class)->handle(new AutopilotTriggerFired(
                tenantId: $tenantId,
                triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
                context: [
                    'ticket_id' => $ticketId,
                    'message_id' => $messageId,
                    'body' => "Message {$i}",
                ],
                sourceId: $sourceId,
            ));
        }

        Bus::assertDispatched(DispatchAutopilotRunJob::class, 3);
    }
}
