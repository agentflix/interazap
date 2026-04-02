<?php

declare(strict_types=1);

use Domain\Ai\Actions\AiNotificationActions;
use Domain\Ai\Contracts\AIServiceInterface;
use Domain\Ai\Jobs\AiAnalyzeSentimentJob;
use Domain\Ai\Services\AiSentimentService;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Redis\Connections\PredisConnection;
use Illuminate\Support\Facades\Redis;

uses(LazilyRefreshDatabase::class);

describe('AiAnalyzeSentimentJob', function (): void {
    it('processes sentiment, applies sliding window and creates alert when critical', function (): void {
        $tenant = PlatformTenant::factory()->create();
        $seller = AuthUser::factory()->create(['tenant_id' => $tenant->id]);

        $ticket = ChatTicket::factory()->forTenant((string) $tenant->id)->create([
            'assigned_to' => $seller->id,
            'protocol' => 'TK-001',
            'sentiment_score' => 90,
        ]);

        /** @var AIServiceInterface&\Mockery\MockInterface $aiService */
        $aiService = Mockery::mock(AIServiceInterface::class);
        $aiService->shouldNotReceive('complete');
        $aiService->shouldReceive('getProvider')->andReturn('openai');

        $service = new AiSentimentService($aiService);

        $defaultRedis = Mockery::mock();
        $defaultRedis->shouldReceive('set')
            ->once()
            ->with("sentiment:cooldown:{$ticket->id}", '1', ['EX' => 30, 'NX'])
            ->andReturn('OK');

        $gatewayMock = Mockery::mock();
        $gatewayMock->shouldReceive('publish')
            ->once()
            ->with('ws.events', Mockery::on(fn (string $payload): bool => str_contains($payload, 'ticket.sentiment_updated')))
            ->andReturn(1);

        $defaultRedis->shouldReceive('set')
            ->once()
            ->with("sentiment:alert:{$ticket->id}", '1', ['EX' => 3600, 'NX'])
            ->andReturn('OK');

        $gatewayMock->shouldReceive('publish')
            ->once()
            ->with('ws.events', Mockery::on(fn (string $payload): bool => str_contains($payload, 'notification.sent')))
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->twice()
            ->withNoArgs()
            ->andReturn($defaultRedis);

        Redis::shouldReceive('connection')
            ->with('gateway')
            ->andReturn($gatewayMock);

        $job = new AiAnalyzeSentimentJob((string) $ticket->id, 'serviço horrível e absurdo', (string) $tenant->id);
        $job->handle($service, new AiNotificationActions);

        $ticket->refresh();

        expect($ticket->sentiment_score)->toBe(81)
            ->and($ticket->sentiment)->toBe('critical')
            ->and($ticket->sentiment_updated_at)->not->toBeNull();

        $this->assertDatabaseHas('ai_seller_notifications', [
            'tenant_id' => (string) $tenant->id,
            'seller_id' => (string) $seller->id,
            'reason' => 'negative_sentiment',
            'priority' => 'urgent',
        ]);
    });

    it('skips processing when cooldown lock is not acquired', function (): void {
        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->forTenant((string) $tenant->id)->create([
            'sentiment_score' => null,
        ]);

        /** @var AIServiceInterface&\Mockery\MockInterface $aiService */
        $aiService = Mockery::mock(AIServiceInterface::class);
        $aiService->shouldNotReceive('complete');
        $aiService->shouldReceive('getProvider')->andReturn('openai');

        $service = new AiSentimentService($aiService);

        $defaultRedis = Mockery::mock();
        $defaultRedis->shouldReceive('set')
            ->once()
            ->with("sentiment:cooldown:{$ticket->id}", '1', ['EX' => 30, 'NX'])
            ->andReturn(null);

        Redis::shouldReceive('connection')
            ->once()
            ->withNoArgs()
            ->andReturn($defaultRedis);

        Redis::shouldNotReceive('publish');

        $job = new AiAnalyzeSentimentJob((string) $ticket->id, 'mensagem', (string) $tenant->id);
        $job->handle($service, new AiNotificationActions);

        $ticket->refresh();

        expect($ticket->sentiment_score)->toBeNull()
            ->and($ticket->sentiment)->toBeNull();
    });

    it('creates sentiment alert using lock options array on non-predis connections', function (): void {
        $tenant = PlatformTenant::factory()->create();
        $seller = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = ChatTicket::factory()->forTenant((string) $tenant->id)->create([
            'assigned_to' => $seller->id,
            'protocol' => 'TK-LOCK',
        ]);

        $defaultRedis = Mockery::mock();
        $defaultRedis->shouldReceive('set')
            ->once()
            ->with("sentiment:alert:{$ticket->id}", '1', ['EX' => 3600, 'NX'])
            ->andReturn('OK');

        $gatewayMock = Mockery::mock();
        $gatewayMock->shouldReceive('publish')
            ->once()
            ->with('ws.events', Mockery::on(fn (string $payload): bool => str_contains($payload, 'notification.sent')))
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->once()
            ->withNoArgs()
            ->andReturn($defaultRedis);

        Redis::shouldReceive('connection')
            ->with('gateway')
            ->andReturn($gatewayMock);

        (new AiNotificationActions)->createSentimentAlert($ticket, 10);

        $this->assertDatabaseHas('ai_seller_notifications', [
            'tenant_id' => (string) $tenant->id,
            'seller_id' => (string) $seller->id,
            'reason' => 'negative_sentiment',
            'priority' => 'urgent',
        ]);
    });

    it('serializes lock options as positional args on predis connections', function (): void {
        $tenant = PlatformTenant::factory()->create();
        $seller = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = ChatTicket::factory()->forTenant((string) $tenant->id)->create([
            'assigned_to' => $seller->id,
            'protocol' => 'TK-PREDIS',
        ]);

        /** @var PredisConnection&\Mockery\MockInterface $predisConnection */
        $predisConnection = Mockery::mock(PredisConnection::class);
        $predisClient = Mockery::mock();
        $predisConnection->shouldReceive('client')
            ->once()
            ->andReturn($predisClient);
        $predisClient->shouldReceive('executeRaw')
            ->once()
            ->with(['SET', "sentiment:alert:{$ticket->id}", '1', 'EX', '3600', 'NX'])
            ->andReturn('OK');

        $gatewayMock = Mockery::mock();
        $gatewayMock->shouldReceive('publish')
            ->once()
            ->with('ws.events', Mockery::on(fn (string $payload): bool => str_contains($payload, 'notification.sent')))
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->once()
            ->withNoArgs()
            ->andReturn($predisConnection);

        Redis::shouldReceive('connection')
            ->with('gateway')
            ->andReturn($gatewayMock);

        (new AiNotificationActions)->createSentimentAlert($ticket, 10);

        $this->assertDatabaseHas('ai_seller_notifications', [
            'tenant_id' => (string) $tenant->id,
            'seller_id' => (string) $seller->id,
            'reason' => 'negative_sentiment',
            'priority' => 'urgent',
        ]);
    });
});
