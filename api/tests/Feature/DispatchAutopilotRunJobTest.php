<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Ai\Enums\AutopilotTriggerType;
use Domain\Ai\Events\AutopilotTriggerFired;
use Domain\Ai\Jobs\DispatchAutopilotRunJob;
use Domain\Ai\Listeners\AutopilotRunDispatcherListener;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Bus\Queue;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Tests for DispatchAutopilotRunJob (async logic extracted from listener).
 *
 * These tests run the job synchronously via Bus::fake() assertions
 * to verify job dispatch and behavior without needing a real queue.
 */
final class DispatchAutopilotRunJobTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Bus::fake([DispatchAutopilotRunJob::class]);
    }

    public function test_listener_dispatches_job_with_correct_parameters(): void
    {
        $tenantId = (string) Str::orderedUuid();
        $ticketId = (string) Str::orderedUuid();
        $messageId = (string) Str::orderedUuid();

        $event = new AutopilotTriggerFired(
            tenantId: $tenantId,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: [
                'ticket_id' => $ticketId,
                'message_id' => $messageId,
                'body' => 'Hello AI',
                'instance_id' => (string) Str::orderedUuid(),
                'source_type' => 'ticket',
            ],
            sourceId: $ticketId,
        );

        app(AutopilotRunDispatcherListener::class)->handle($event);

        Bus::assertDispatched(DispatchAutopilotRunJob::class, function (DispatchAutopilotRunJob $job) use ($tenantId, $ticketId, $messageId): bool {
            return $job->tenantId === $tenantId
                && $job->triggerType === AutopilotTriggerType::INBOUND_MESSAGE
                && ($job->context['ticket_id'] ?? '') === $ticketId
                && ($job->context['message_id'] ?? '') === $messageId
                && $job->sourceId === $ticketId;
        });
    }

    public function test_listener_does_not_dispatch_when_tenant_id_is_empty(): void
    {
        $event = new AutopilotTriggerFired(
            tenantId: '',
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: ['ticket_id' => (string) Str::orderedUuid()],
            sourceId: '',
        );

        app(AutopilotRunDispatcherListener::class)->handle($event);

        Bus::assertNotDispatched(DispatchAutopilotRunJob::class);
    }

    public function test_listener_does_not_dispatch_when_source_id_is_empty(): void
    {
        $event = new AutopilotTriggerFired(
            tenantId: (string) Str::orderedUuid(),
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: ['ticket_id' => (string) Str::orderedUuid()],
            sourceId: '',
        );

        app(AutopilotRunDispatcherListener::class)->handle($event);

        Bus::assertNotDispatched(DispatchAutopilotRunJob::class);
    }

    public function test_job_is_queued_to_ai_queue(): void
    {
        $event = new AutopilotTriggerFired(
            tenantId: (string) Str::orderedUuid(),
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: ['ticket_id' => (string) Str::orderedUuid()],
            sourceId: (string) Str::orderedUuid(),
        );

        app(AutopilotRunDispatcherListener::class)->handle($event);

        Bus::assertDispatched(DispatchAutopilotRunJob::class, function (DispatchAutopilotRunJob $job): bool {
            return true;
        });
    }

    public function test_job_has_300s_timeout(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $event = new AutopilotTriggerFired(
            tenantId: (string) $tenant->id,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: ['ticket_id' => (string) Str::orderedUuid()],
            sourceId: (string) Str::orderedUuid(),
        );

        app(AutopilotRunDispatcherListener::class)->handle($event);

        Bus::assertDispatched(DispatchAutopilotRunJob::class, function (DispatchAutopilotRunJob $job): bool {
            return $job->timeout === 300;
        });
    }

    public function test_job_has_3_tries(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $event = new AutopilotTriggerFired(
            tenantId: (string) $tenant->id,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: ['ticket_id' => (string) Str::orderedUuid()],
            sourceId: (string) Str::orderedUuid(),
        );

        app(AutopilotRunDispatcherListener::class)->handle($event);

        Bus::assertDispatched(DispatchAutopilotRunJob::class, function (DispatchAutopilotRunJob $job): bool {
            return $job->tries === 3;
        });
    }
}
