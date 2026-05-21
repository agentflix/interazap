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

final class CorrelationIdEndToEndTest extends TestCase
{
    use RefreshDatabase;

    public function test_correlation_id_is_persisted_and_published_to_gateway_stream(): void
    {
        Bus::fake();

        $tenant = PlatformTenant::factory()->create();
        AiAgent::factory()->create([
            'tenant_id' => (string) $tenant->id,
            'type' => 'general',
            'is_active' => true,
            'model_id' => 'gpt-4o-mini',
        ]);

        $ticketId = (string) Str::orderedUuid();
        $messageId = (string) Str::orderedUuid();
        $correlationId = (string) Str::orderedUuid();

        $this->cleanupDispatchLock((string) $tenant->id, $messageId);

        $job = new DispatchAutopilotRunJob(
            tenantId: (string) $tenant->id,
            triggerType: AutopilotTriggerType::INBOUND_MESSAGE,
            context: [
                'ticket_id' => $ticketId,
                'message_id' => $messageId,
                'body' => 'validar correlation id',
                'instance_id' => (string) Str::orderedUuid(),
                'source_type' => 'ticket',
            ],
            sourceId: $ticketId,
            correlationId: $correlationId
        );

        $this->runDispatchJob($job);

        $run = AiAutopilotRun::query()
            ->where('tenant_id', (string) $tenant->id)
            ->first();

        $this->assertNotNull($run);
        $this->assertSame($correlationId, (string) $run->correlation_id);
        $this->assertSame($correlationId, (string) data_get($run->input_context, 'correlation_id'));

        $streamEntry = $this->findRunRequestStreamEntryByRunId((string) $run->id);

        $this->assertNotNull($streamEntry);
        $this->assertSame($correlationId, (string) data_get($streamEntry, 'correlation_id'));
        $this->assertSame((string) $run->id, (string) data_get($streamEntry, 'run_id'));
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

    /**
     * @return array<string, mixed>|null
     */
    private function findRunRequestStreamEntryByRunId(string $runId): ?array
    {
        $entries = $this->gatewayRedis()->command('XREVRANGE', ['ai.run.request', '+', '-', 'COUNT', 100]);

        if (! is_array($entries)) {
            return null;
        }

        foreach ($entries as $streamId => $entry) {
            if (is_array($entry) && array_is_list($entry) && count($entry) >= 2 && is_string($entry[0] ?? null) && is_array($entry[1] ?? null)) {
                $resolvedStreamId = (string) $entry[0];
                $normalizedFields = $this->normalizeStreamFields($entry[1]);
            } elseif (is_string($streamId) && is_array($entry)) {
                $resolvedStreamId = $streamId;
                $normalizedFields = $this->normalizeStreamFields($entry);
            } else {
                continue;
            }

            if ((string) ($normalizedFields['run_id'] ?? '') === $runId) {
                $normalizedFields['_stream_id'] = $resolvedStreamId;

                return $normalizedFields;
            }
        }

        return null;
    }

    /**
     * @param  array<int|string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function normalizeStreamFields(array $fields): array
    {
        if (! array_is_list($fields)) {
            $normalized = [];
            foreach ($fields as $key => $value) {
                if (! is_string($key)) {
                    continue;
                }

                $normalized[$key] = $value;
            }

            return $normalized;
        }

        $normalized = [];
        $count = count($fields);

        for ($i = 0; $i < $count; $i += 2) {
            $key = $fields[$i] ?? null;
            $value = $fields[$i + 1] ?? null;

            if (! is_string($key)) {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
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
