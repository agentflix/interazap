<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Ai\Enums\AutopilotTriggerType;
use Domain\Ai\Events\AiRunFailed;
use Domain\Ai\Jobs\DispatchAutopilotRunJob;
use Domain\Ai\Models\AiAutopilotRun;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

final class DispatchAutopilotRunJobFailedHandlerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_failed_handler_marks_run_as_failed_and_dispatches_event(): void
    {
        Event::fake([AiRunFailed::class]);
        Log::spy();

        $run = AiAutopilotRun::factory()->create([
            'status' => 'running',
            'completed_at' => null,
            'input_context' => [
                'ticket_id' => 'ticket-failed-1',
                'correlation_id' => 'corr-failed-1',
            ],
        ]);

        $job = new DispatchAutopilotRunJob(
            tenantId: (string) $run->tenant_id,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: ['ticket_id' => 'ticket-failed-1'],
            sourceId: (string) Str::orderedUuid(),
        );
        $job->runId = (string) $run->id;

        $job->failed(new \RuntimeException('Retries exhausted'));

        $run->refresh();

        $this->assertSame('failed', $run->status);
        $this->assertSame('Retries exhausted', data_get($run->output, 'error'));
        $this->assertSame('dispatch_job_failed', data_get($run->output, 'error_code'));
        $this->assertSame(\RuntimeException::class, data_get($run->output, 'exception_class'));
        $this->assertNotNull($run->completed_at);

        Event::assertDispatched(AiRunFailed::class, fn (AiRunFailed $event): bool => $event->runId === (string) $run->id
            && $event->tenantId === (string) $run->tenant_id
            && $event->ticketId === 'ticket-failed-1'
            && $event->correlationId === 'corr-failed-1'
            && $event->errorCode === 'dispatch_job_failed');

        Log::shouldHaveReceived('error')
            ->once()
            ->with(
                '[DispatchAutopilotRunJob] Job failed after retries exhausted',
                Mockery::on(function (array $context) use ($run): bool {
                    return ($context['run_id'] ?? null) === (string) $run->id
                        && ($context['tenant_id'] ?? null) === (string) $run->tenant_id
                        && ($context['correlation_id'] ?? null) === 'corr-failed-1'
                        && ($context['exception_class'] ?? null) === \RuntimeException::class
                        && ($context['exception_message'] ?? null) === 'Retries exhausted';
                }),
            );
    }

    public function test_failed_handler_recovers_run_id_from_cache_when_property_is_missing(): void
    {
        Event::fake([AiRunFailed::class]);
        Log::spy();

        $sourceId = (string) Str::orderedUuid();
        $run = AiAutopilotRun::factory()->create([
            'status' => 'running',
            'completed_at' => null,
            'input_context' => [
                'ticket_id' => 'ticket-failed-cache-1',
                'correlation_id' => 'corr-failed-cache-1',
            ],
        ]);

        Cache::put(
            sprintf(
                'autopilot:dispatch:run-id:tenant:%s:source:%s',
                (string) $run->tenant_id,
                $sourceId
            ),
            (string) $run->id,
            now()->addHour()
        );

        $job = new DispatchAutopilotRunJob(
            tenantId: (string) $run->tenant_id,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: ['ticket_id' => 'ticket-failed-cache-1'],
            sourceId: $sourceId,
        );

        $job->failed(new \RuntimeException('Retries exhausted from cache'));

        $run->refresh();

        $this->assertSame('failed', $run->status);
        $this->assertSame('Retries exhausted from cache', data_get($run->output, 'error'));
        $this->assertFalse(Cache::has(sprintf(
            'autopilot:dispatch:run-id:tenant:%s:source:%s',
            (string) $run->tenant_id,
            $sourceId
        )));

        Event::assertDispatched(AiRunFailed::class, fn (AiRunFailed $event): bool => $event->runId === (string) $run->id
            && $event->ticketId === 'ticket-failed-cache-1'
            && $event->correlationId === 'corr-failed-cache-1');
    }
}
