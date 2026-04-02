<?php

declare(strict_types=1);

namespace Tests\Unit\Gateway;

use Domain\Gateway\DTOs\GatewayMessage;
use Domain\Gateway\DTOs\GatewayResponse;
use Domain\Gateway\Enums\GatewayDomain;
use Domain\Gateway\Exceptions\GatewayTimeoutException;
use Domain\Gateway\Exceptions\ProviderException;
use Domain\Gateway\Services\RedisGatewayClient;
use Illuminate\Redis\Connections\Connection;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class RedisGatewayClientTest extends TestCase
{
    private RedisGatewayClient $client;

    private $redisMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->redisMock = Mockery::mock(Connection::class);
        Redis::shouldReceive('connection')
            ->andReturn($this->redisMock);

        $this->client = new RedisGatewayClient;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_dispatch_publishes_message_to_redis_stream(): void
    {
        $message = GatewayMessage::create(
            domain: GatewayDomain::AI,
            action: 'completion',
            provider: 'openai',
            payload: ['prompt' => 'Hello'],
        );

        $this->redisMock
            ->shouldReceive('xadd')
            ->once()
            ->with('ai.run.request', '*', Mockery::type('array'));

        $correlationId = $this->client->dispatch($message);

        $this->assertSame($message->correlationId, $correlationId);
    }

    public function test_dispatch_uses_correct_stream_for_whatsapp_domain(): void
    {
        $message = GatewayMessage::create(
            domain: GatewayDomain::WHATSAPP,
            action: 'send',
            provider: 'uazapi',
            payload: ['to' => '5511999999999', 'text' => 'Hello'],
        );

        $this->redisMock
            ->shouldReceive('xadd')
            ->once()
            ->with('whatsapp.message.request', '*', Mockery::type('array'));

        $correlationId = $this->client->dispatch($message);

        $this->assertSame($message->correlationId, $correlationId);
    }

    public function test_send_throws_timeout_exception_when_no_response(): void
    {
        $message = GatewayMessage::create(
            domain: GatewayDomain::AI,
            action: 'completion',
            provider: 'openai',
            payload: ['prompt' => 'Hello'],
        );

        $this->redisMock
            ->shouldReceive('xadd')
            ->once();

        $this->redisMock
            ->shouldReceive('command')
            ->with('XREAD', Mockery::type('array'))
            ->once()
            ->andReturn(null);

        $this->expectException(GatewayTimeoutException::class);
        $this->expectExceptionMessage('Gateway did not respond within 1s');

        $this->client->send($message, 1);
    }

    public function test_send_returns_response_on_success(): void
    {
        $message = GatewayMessage::create(
            domain: GatewayDomain::AI,
            action: 'completion',
            provider: 'openai',
            payload: ['prompt' => 'Hello'],
        );

        $responseStream = config('gateway.streams.ai_response_prefix', 'ai.run.response:').$message->correlationId;

        $this->redisMock
            ->shouldReceive('xadd')
            ->once();

        $this->redisMock
            ->shouldReceive('command')
            ->with('XREAD', Mockery::type('array'))
            ->once()
            ->andReturn([
                [
                    $responseStream,
                    [
                        [
                            'msg-id-1',
                            [
                                'correlationId', $message->correlationId,
                                'timestamp', now()->toIso8601String(),
                                'success', 'true',
                                'data', json_encode(['content' => 'Response text']),
                            ],
                        ],
                    ],
                ],
            ]);

        $this->redisMock
            ->shouldReceive('del')
            ->once()
            ->with($responseStream);

        $response = $this->client->send($message, 10);

        $this->assertInstanceOf(GatewayResponse::class, $response);
        $this->assertTrue($response->success);
        $this->assertSame($message->correlationId, $response->correlationId);
    }

    public function test_send_throws_provider_exception_on_error_response(): void
    {
        $message = GatewayMessage::create(
            domain: GatewayDomain::AI,
            action: 'completion',
            provider: 'openai',
            payload: ['prompt' => 'Hello'],
        );

        $responseStream = config('gateway.streams.ai_response_prefix', 'ai.run.response:').$message->correlationId;

        $this->redisMock
            ->shouldReceive('xadd')
            ->once();

        $this->redisMock
            ->shouldReceive('command')
            ->with('XREAD', Mockery::type('array'))
            ->once()
            ->andReturn([
                [
                    $responseStream,
                    [
                        [
                            'msg-id-1',
                            [
                                'correlationId', $message->correlationId,
                                'timestamp', now()->toIso8601String(),
                                'success', 'false',
                                'error', json_encode([
                                    'code' => 'RATE_LIMIT',
                                    'message' => 'Too many requests',
                                ]),
                            ],
                        ],
                    ],
                ],
            ]);

        $this->redisMock
            ->shouldReceive('del')
            ->once()
            ->with($responseStream);

        $this->expectException(ProviderException::class);

        $this->client->send($message, 10);
    }

    public function test_message_to_array_serializes_correctly(): void
    {
        $message = GatewayMessage::create(
            domain: GatewayDomain::AI,
            action: 'chat',
            provider: 'anthropic',
            payload: ['messages' => [['role' => 'user', 'content' => 'Hi']]],
            metadata: ['tenant_id' => 'tenant-123'],
        );

        $array = $message->toArray();

        $this->assertSame($message->correlationId, $array['correlationId']);
        $this->assertSame('ai', $array['domain']);
        $this->assertSame('chat', $array['action']);
        $this->assertSame('anthropic', $array['provider']);
        $this->assertIsString($array['payload']);
        $this->assertIsString($array['metadata']);
    }

    public function test_send_cleans_up_response_stream(): void
    {
        $message = GatewayMessage::create(
            domain: GatewayDomain::AI,
            action: 'completion',
            provider: 'openai',
            payload: ['prompt' => 'Test'],
        );

        $responseStream = config('gateway.streams.ai_response_prefix', 'ai.run.response:').$message->correlationId;

        $this->redisMock
            ->shouldReceive('xadd')
            ->once();

        $this->redisMock
            ->shouldReceive('command')
            ->with('XREAD', Mockery::type('array'))
            ->once()
            ->andReturn([
                [
                    $responseStream,
                    [
                        [
                            'msg-id-1',
                            [
                                'correlationId', $message->correlationId,
                                'timestamp', now()->toIso8601String(),
                                'success', 'true',
                                'data', json_encode(['content' => 'Done']),
                            ],
                        ],
                    ],
                ],
            ]);

        // Verify del is called to clean up
        $this->redisMock
            ->shouldReceive('del')
            ->once()
            ->with($responseStream);

        $response = $this->client->send($message, 10);

        $this->assertTrue($response->success);
    }

    public function test_normalize_stream_fields_serializes_non_scalar_values(): void
    {
        $reflection = new \ReflectionClass($this->client);
        $method = $reflection->getMethod('normalizeStreamFields');

        /** @var array<string, string> $normalized */
        $normalized = $method->invoke($this->client, [
            'string' => 'abc',
            'int' => 10,
            'float' => 2.5,
            'bool_true' => true,
            'bool_false' => false,
            'null' => null,
            'array' => ['x' => 1],
        ]);

        $this->assertSame('abc', $normalized['string']);
        $this->assertSame('10', $normalized['int']);
        $this->assertSame('2.5', $normalized['float']);
        $this->assertSame('1', $normalized['bool_true']);
        $this->assertSame('0', $normalized['bool_false']);
        $this->assertSame('', $normalized['null']);
        $this->assertSame('{"x":1}', $normalized['array']);
    }
}
