<?php

declare(strict_types=1);

namespace Tests\E2E\Autopilot;

use Domain\Ai\Enums\AutopilotTriggerType;
use Domain\Ai\Jobs\DispatchAutopilotRunJob;
use Domain\Ai\Models\AiAgent;
use Domain\Ai\Models\AiAutopilotRun;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Tests\TestCase;

final class ConcurrentWebhooksTest extends TestCase
{
    use RefreshDatabase;

    public function test_duplicate_message_dispatch_creates_single_run(): void
    {
        Bus::fake();

        $tenant = PlatformTenant::factory()->create();
        AiAgent::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'type' => 'general',
            'is_active' => true,
            'model_id' => 'gpt-4o-mini',
        ]);

        $messageId = (string) Str::orderedUuid();
        $ticketId = (string) Str::orderedUuid();
        $firstCorrelationId = (string) Str::orderedUuid();
        $secondCorrelationId = (string) Str::orderedUuid();

        $this->cleanupDispatchLock((string) $tenant->id, $messageId);

        $firstJob = new DispatchAutopilotRunJob(
            tenantId: (string) $tenant->id,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: [
                'ticket_id' => $ticketId,
                'message_id' => $messageId,
                'body' => 'primeiro webhook',
                'instance_id' => (string) Str::orderedUuid(),
                'source_type' => 'ticket',
            ],
            sourceId: $ticketId,
            correlationId: $firstCorrelationId
        );

        $secondJob = new DispatchAutopilotRunJob(
            tenantId: (string) $tenant->id,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: [
                'ticket_id' => $ticketId,
                'message_id' => $messageId,
                'body' => 'segundo webhook',
                'instance_id' => (string) Str::orderedUuid(),
                'source_type' => 'ticket',
            ],
            sourceId: $ticketId,
            correlationId: $secondCorrelationId
        );

        $this->runDispatchJob($firstJob);
        $this->runDispatchJob($secondJob);

        $runs = AiAutopilotRun::query()
            ->where('tenant_id', (string) $tenant->id)
            ->get();

        $this->assertCount(1, $runs);
        $this->assertSame($firstCorrelationId, (string) $runs->first()?->correlation_id);
    }

    private function runDispatchJob(DispatchAutopilotRunJob $job): void
    {
        $job->handle(
            app(\Domain\Chat\Services\ChatAiActivityService::class),
            app(\Domain\Ai\Services\AutopilotRunSnapshotResolver::class),
            app(\Domain\Ai\Services\AutopilotRunStreamPublisher::class),
            app(\Domain\Ai\Services\AiContextBuilderService::class),
        );
    }

    private function cleanupDispatchLock(string $tenantId, string $messageId): void
    {
        $lockKey = sprintf('autopilot:lock:tenant:%s:msg:%s', $tenantId, $messageId);
        $this->gatewayRedis()->del($lockKey);
    }

    private function gatewayRedis(): Connection
    {
        /** @var Connection $redis */
        $redis = Redis::connection(config('gateway.redis.connection', 'gateway'));

        return $redis;
    }
}
