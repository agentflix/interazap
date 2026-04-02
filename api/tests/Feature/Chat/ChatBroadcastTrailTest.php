<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use Domain\Chat\Services\ChatActivityBroadcastService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

/**
 * OS-021 — Revalidação da trilha de broadcasts backend chat.
 *
 * Verifica que ChatActivityBroadcastService:
 *   1. Emite o envelope chat.activity nas rooms corretas (ticket + tenant).
 *   2. Filtra subevents com tipo inválido sem lançar exceção.
 *   3. Não faz publish quando a lista de subevents está vazia.
 *   4. Inclui tenant_id e ticketId explícitos no payload.
 */
final class ChatBroadcastTrailTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * OS-021: Subevent válido deve ser publicado via Redis PubSub nas rooms
     * ticket:{id} e tenant:{id} com o envelope chat.activity.
     */
    public function test_valid_subevent_is_published_to_correct_redis_rooms(): void
    {
        $ticketId = (string) Str::orderedUuid();
        $tenantId = (string) Str::orderedUuid();

        $publishedPayloads = [];

        $redisConnection = Mockery::mock();
        $redisConnection->shouldReceive('publish')
            ->twice() // ticket room + tenant room
            ->with('ws.events', Mockery::on(function (string $raw) use (&$publishedPayloads): bool {
                $publishedPayloads[] = json_decode($raw, true);

                return true;
            }))
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->atLeast()->once()
            ->andReturn($redisConnection);

        app(ChatActivityBroadcastService::class)->emit(
            $ticketId,
            [
                [
                    'type' => 'msg.received',
                    'data' => [
                        'tenant_id' => $tenantId,
                        'ticket_id' => $ticketId,
                        'message_id' => (string) Str::orderedUuid(),
                        'body' => 'Hello',
                    ],
                ],
            ],
            $tenantId,
        );

        $this->assertCount(2, $publishedPayloads, 'Deve publicar em dois rooms: ticket e tenant');

        // Ambos os envelopes devem identificar o evento e o contexto
        foreach ($publishedPayloads as $decoded) {
            $this->assertSame('chat.activity', data_get($decoded, 'event'));
            $this->assertSame($tenantId, data_get($decoded, 'tenant_id'));
            $this->assertSame($ticketId, data_get($decoded, 'data.ticketId'));
            $this->assertSame('msg.received', data_get($decoded, 'data.subevents.0.type'));
        }
        $allRooms = array_filter(array_column($publishedPayloads, 'rooms'), is_array(...));
        $flatRooms = array_merge(...array_values($allRooms));
        $this->assertContains("ticket:{$ticketId}", $flatRooms);
        $this->assertContains("tenant:{$tenantId}", $flatRooms);
    }

    /**
     * OS-021: Subevents com tipo inválido devem ser silenciosamente descartados
     * sem erro e sem publish (envelope vazio não é emitido).
     */
    public function test_invalid_subevent_types_are_filtered_and_no_publish_occurs(): void
    {
        $ticketId = (string) Str::orderedUuid();
        $tenantId = (string) Str::orderedUuid();

        // Redis publish NÃO deve ser chamado — todos os subevents são inválidos
        Redis::shouldReceive('connection')->never();

        app(ChatActivityBroadcastService::class)->emit(
            $ticketId,
            [
                [
                    'type' => 'invalid.event.type',
                    'data' => ['tenant_id' => $tenantId, 'foo' => 'bar'],
                ],
                [
                    'type' => 'another.unknown',
                    'data' => ['tenant_id' => $tenantId],
                ],
            ],
            $tenantId,
        );

        // Se chegou aqui sem erro, o filtro funcionou corretamente.
        $this->assertTrue(true);
    }

    /**
     * OS-021: Lista de subevents vazia não deve gerar publish.
     */
    public function test_empty_subevents_does_not_publish(): void
    {
        Redis::shouldReceive('connection')->never();

        app(ChatActivityBroadcastService::class)->emit(
            (string) Str::orderedUuid(),
            [],
            (string) Str::orderedUuid(),
        );

        $this->assertTrue(true);
    }

    /**
     * OS-021: Tipos de AI activity (started/completed/failed/rejected) são
     * subevent types reconhecidos pelo contrato do broadcast.
     *
     * Envia todos de uma vez e verifica que cada um aparece no payload publicado.
     */
    public function test_ai_activity_subevent_types_are_valid_and_broadcast(): void
    {
        $ticketId = (string) Str::orderedUuid();
        $tenantId = (string) Str::orderedUuid();

        $allPublished = [];

        $redisConnection = Mockery::mock();
        $redisConnection->shouldReceive('publish')
            ->atLeast()->once()
            ->with('ws.events', Mockery::on(function (string $raw) use (&$allPublished): bool {
                $decoded = json_decode($raw, true);
                $subevents = data_get($decoded, 'data.subevents', []);
                foreach ($subevents as $subevent) {
                    $allPublished[] = $subevent['type'] ?? '';
                }

                return true;
            }))
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->atLeast()->once()
            ->andReturn($redisConnection);

        // Emit all four AI types in a single call to avoid Mockery re-entrant issues
        app(ChatActivityBroadcastService::class)->emit(
            $ticketId,
            [
                ['type' => 'ai.processing.started',   'data' => ['tenant_id' => $tenantId, 'run_id' => 'r1']],
                ['type' => 'ai.processing.completed',  'data' => ['tenant_id' => $tenantId, 'run_id' => 'r2']],
                ['type' => 'ai.processing.failed',    'data' => ['tenant_id' => $tenantId, 'run_id' => 'r3']],
                ['type' => 'ai.processing.rejected',   'data' => ['tenant_id' => $tenantId, 'run_id' => 'r4']],
            ],
            $tenantId,
        );

        foreach (['ai.processing.started', 'ai.processing.completed', 'ai.processing.failed', 'ai.processing.rejected'] as $expectedType) {
            $this->assertContains($expectedType, $allPublished, "Subevent type '{$expectedType}' should be present in broadcast payload");
        }
    }
}
