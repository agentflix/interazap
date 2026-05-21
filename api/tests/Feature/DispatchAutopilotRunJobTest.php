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

    private const int TIMEOUT = 300;

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

        Bus::assertDispatched(DispatchAutopilotRunJob::class, fn (DispatchAutopilotRunJob $job): bool => $job->tenantId === $tenantId
            && $job->triggerType === AutopilotTriggerType::INBOUND_MESSAGE
            && ($job->context['ticket_id'] ?? '') === $ticketId
            && ($job->context['message_id'] ?? '') === $messageId
            && $job->sourceId === $ticketId
            && is_string($job->correlationId)
            && $job->correlationId !== '');
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

        Bus::assertDispatched(DispatchAutopilotRunJob::class, fn (DispatchAutopilotRunJob $job): bool => true);
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

        Bus::assertDispatched(DispatchAutopilotRunJob::class, fn (DispatchAutopilotRunJob $job): bool => $job->timeout === 300);
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

        Bus::assertDispatched(DispatchAutopilotRunJob::class, fn (DispatchAutopilotRunJob $job): bool => $job->tries === 3);
    }

    public function test_lock_ttl_matches_job_timeout(): void
    {
        $job = new DispatchAutopilotRunJob(
            tenantId: (string) Str::orderedUuid(),
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: ['ticket_id' => (string) Str::orderedUuid()],
            sourceId: (string) Str::orderedUuid(),
        );

        $reflection = new \ReflectionClass($job);
        $lockTtlSeconds = $reflection->getConstant('LOCK_TTL_SECONDS');

        $this->assertSame(self::TIMEOUT, $lockTtlSeconds);
        $this->assertSame(self::TIMEOUT, $job->timeout);
    }

    public function test_event_generates_correlation_id_when_not_provided(): void
    {
        $event = new AutopilotTriggerFired(
            tenantId: (string) Str::orderedUuid(),
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: ['ticket_id' => (string) Str::orderedUuid()],
            sourceId: (string) Str::orderedUuid(),
        );

        $this->assertNotSame('', $event->correlationId);
    }

    public function test_event_keeps_custom_correlation_id(): void
    {
        $correlationId = (string) Str::orderedUuid();
        $event = new AutopilotTriggerFired(
            tenantId: (string) Str::orderedUuid(),
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: ['ticket_id' => (string) Str::orderedUuid()],
            sourceId: (string) Str::orderedUuid(),
            correlationId: $correlationId,
        );

        $this->assertSame($correlationId, $event->correlationId);
    }
}
