<?php

declare(strict_types=1);

namespace Tests\Unit\Chat\Services;

use Domain\Chat\Services\WebChatRedisPublisher;
use Domain\Shared\Services\GatewayBroadcastService;
use Mockery;
use Tests\TestCase;

final class WebChatRedisPublisherTest extends TestCase
{
    private $mockBroadcastService;

    private WebChatRedisPublisher $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mockBroadcastService = Mockery::mock(GatewayBroadcastService::class);
        $this->service = new WebChatRedisPublisher($this->mockBroadcastService);
    }

    public function test_publishes_a_i_response_to_redis_with_correct_event_and_room(): void
    {
        $sessionId = 'session-123';
        $message = [
            'id' => 'msg-456',
            'tenant_id' => 'tenant-789',
            'content' => 'Olá, como posso ajudar?',
            'source' => 'ai',
            'direction' => 'outgoing',
        ];

        $this->mockBroadcastService
            ->shouldReceive('broadcastEvent')
            ->once()
            ->withArgs(fn ($event, $data, $room): bool => $event === 'webchat:ai_response'
                && $data['session_id'] === $sessionId
                && $data['tenant_id'] === $message['tenant_id']
                && $data['message'] === $message
                && $room === 'session:'.$sessionId)
            ->byDefault();

        $this->service->publishAiResponse($sessionId, $message);

        // Assert that broadcastEvent was called with the expected arguments
        $this->mockBroadcastService
            ->shouldHaveReceived('broadcastEvent')
            ->withArgs(fn ($event, $data, $room): bool => $event === 'webchat:ai_response'
                && $data['session_id'] === $sessionId
                && $data['tenant_id'] === $message['tenant_id']
                && $data['message'] === $message
                && $room === 'session:'.$sessionId)
            ->once();

        // Pest requires explicit assertion
        $this->assertTrue(true);
    }

    public function test_handles_broadcast_exception_gracefully(): void
    {
        $sessionId = 'session-123';
        $message = ['tenant_id' => 'tenant-789', 'content' => 'test'];

        $this->mockBroadcastService
            ->shouldReceive('broadcastEvent')
            ->once()
            ->andThrow(new \RuntimeException('Redis connection failed'));

        // Should not throw - exception is caught internally
        $this->service->publishAiResponse($sessionId, $message);

        // Verify that broadcastEvent was called (exception was caught)
        $this->mockBroadcastService
            ->shouldHaveReceived('broadcastEvent')
            ->once();

        // Pest requires explicit assertion
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
