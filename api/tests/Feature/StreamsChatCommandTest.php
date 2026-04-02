<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Console\Commands\ConsumeChatStreamCommand;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class StreamsChatCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_consumes_chat_stream_without_raw_webhook_persistence(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $payload = [
            'tenant_id' => $tenant->id,
            'provider' => 'uazapi',
            'event_type' => 'messages',
            'direction' => 'incoming',
            'instance_webhook_token' => 'tok-123',
            'message' => ['body' => 'hello'],
        ];

        Redis::shouldReceive('xgroup')->andReturnTrue();
        Redis::shouldReceive('connection')->andReturnSelf();
        Redis::shouldReceive('xreadgroup')->once()->andReturn([
            'chat.inbound_message_received' => [
                '1-0' => [
                    'tenant_id' => (string) $payload['tenant_id'],
                    'provider' => $payload['provider'],
                    'event_type' => $payload['event_type'],
                    'direction' => $payload['direction'],
                    'instance_webhook_token' => $payload['instance_webhook_token'],
                    'message' => json_encode($payload['message'], JSON_THROW_ON_ERROR),
                    'idempotency_key' => 'abc',
                ],
            ],
        ]);
        Redis::shouldReceive('xack')->once();

        Artisan::call(ConsumeChatStreamCommand::class, ['--once' => true]);

        $this->assertDatabaseCount('shared_webhook_events', 0);
    }
}
