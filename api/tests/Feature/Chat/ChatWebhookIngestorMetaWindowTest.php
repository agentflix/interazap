<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use Domain\Chat\Actions\ChatWebhookIngestor;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatGatewayService;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Testes de integração da janela de atendimento Meta (24h/72h CTWA) via
 * {@see ChatWebhookIngestor}.
 *
 * Cobre: renovação a partir de inbound (com e sem referral), aplicação da
 * janela a partir de status.window, e o status `failed` não mascarado.
 */
class ChatWebhookIngestorMetaWindowTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mock(ChatGatewayService::class, function ($mock): void {
            $mock->shouldReceive('downloadMedia')->andReturn([]);
            $mock->shouldIgnoreMissing();
        });
    }

    private function createMetaInstance(string $tenantId): ChatInstance
    {
        return ChatInstance::factory()->create([
            'tenant_id' => $tenantId,
            'provider' => 'meta',
            'webhook_token' => 'meta-webhook-token',
            'settings_json' => [
                'phone_number_id' => '1234567890',
                'access_token' => 'secret-token',
            ],
        ]);
    }

    public function test_inbound_without_referral_opens_24h_window_from_message_timestamp(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;
        $instance = $this->createMetaInstance($tenantId);

        $payload = [
            'provider' => 'meta',
            'event_type' => 'messages',
            'instance_id' => (string) $instance->id,
            'tenant_id' => $tenantId,
            'direction' => 'incoming',
            'message' => [
                'id' => 'wamid.INBOUND1',
                'body' => 'Olá, preciso de ajuda',
                'type' => 'text',
                'chatid' => '5511999999999@wa.gw',
                'fromMe' => false,
                'timestamp' => '2026-07-21T10:00:00Z',
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $ticket = ChatTicket::query()->first();
        $this->assertNotNull($ticket);
        $this->assertSame('24h', $ticket->meta_window_type);
        $this->assertNotNull($ticket->meta_window_expires_at);
        $this->assertTrue(
            $ticket->meta_window_expires_at->equalTo(Date::parse('2026-07-21T10:00:00Z')->addHours(24))
        );
    }

    public function test_inbound_with_referral_opens_72h_window_and_persists_referral_metadata(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;
        $instance = $this->createMetaInstance($tenantId);

        $payload = [
            'provider' => 'meta',
            'event_type' => 'messages',
            'instance_id' => (string) $instance->id,
            'tenant_id' => $tenantId,
            'direction' => 'incoming',
            'message' => [
                'id' => 'wamid.INBOUND2',
                'body' => 'Vi o anúncio, quero saber mais',
                'type' => 'text',
                'chatid' => '5511988888888@wa.gw',
                'fromMe' => false,
                'timestamp' => '2026-07-21T10:00:00Z',
                'referral' => [
                    'source_id' => 'ad-999',
                    'source_type' => 'ad',
                    'headline' => 'Compre agora',
                    'ctwa_clid' => 'clid-xyz',
                ],
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $ticket = ChatTicket::query()->first();
        $this->assertNotNull($ticket);
        $this->assertSame('72h', $ticket->meta_window_type);
        $this->assertTrue(
            $ticket->meta_window_expires_at->equalTo(Date::parse('2026-07-21T10:00:00Z')->addHours(72))
        );
        $this->assertSame('clid-xyz', $ticket->meta_referral_ctwa_clid);
        $this->assertSame('ad-999', $ticket->meta_referral_source_id);
    }

    public function test_status_with_window_applies_meta_window_to_ticket(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;
        $instance = $this->createMetaInstance($tenantId);

        // Inbound inicial para criar ticket + mensagem com external_id conhecido.
        app(ChatWebhookIngestor::class)->ingest($tenantId, [
            'provider' => 'meta',
            'event_type' => 'messages',
            'instance_id' => (string) $instance->id,
            'tenant_id' => $tenantId,
            'direction' => 'incoming',
            'message' => [
                'id' => 'wamid.STATUSWINDOW1',
                'body' => 'Oi',
                'type' => 'text',
                'chatid' => '5511977777777@wa.gw',
                'fromMe' => false,
                'timestamp' => '2026-07-21T10:00:00Z',
            ],
        ]);

        // Status de saída reporta uma janela de 72h (CTWA), maior que a 24h vigente.
        app(ChatWebhookIngestor::class)->ingest($tenantId, [
            'provider' => 'meta',
            'event_type' => 'status',
            'tenant_id' => $tenantId,
            'status' => [
                'messageId' => 'wamid.STATUSWINDOW1',
                'status' => 'delivered',
                'window' => [
                    'expiresAt' => '2026-07-24T10:00:00Z',
                    'type' => '72h',
                ],
            ],
        ]);

        $ticket = ChatTicket::query()->first();
        $this->assertNotNull($ticket);
        $this->assertSame('72h', $ticket->meta_window_type);
        $this->assertTrue(
            $ticket->meta_window_expires_at->equalTo(Date::parse('2026-07-24T10:00:00Z'))
        );
    }

    public function test_failed_status_is_persisted_as_failed_with_errors_in_metadata(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;
        $instance = $this->createMetaInstance($tenantId);

        app(ChatWebhookIngestor::class)->ingest($tenantId, [
            'provider' => 'meta',
            'event_type' => 'messages',
            'instance_id' => (string) $instance->id,
            'tenant_id' => $tenantId,
            'direction' => 'outgoing',
            'message' => [
                'id' => 'wamid.FAILEDMSG1',
                'body' => 'Promoção especial',
                'type' => 'text',
                'chatid' => '5511966666666@wa.gw',
                'fromMe' => true,
            ],
        ]);

        app(ChatWebhookIngestor::class)->ingest($tenantId, [
            'provider' => 'meta',
            'event_type' => 'status',
            'tenant_id' => $tenantId,
            'status' => [
                'messageId' => 'wamid.FAILEDMSG1',
                'status' => 'failed',
                'errors' => [
                    [
                        'code' => 131047,
                        'title' => 'Re-engagement message',
                    ],
                ],
            ],
        ]);

        $message = ChatMessage::query()->where('external_id', 'wamid.FAILEDMSG1')->first();
        $this->assertNotNull($message);
        $this->assertSame('failed', $message->status);
        $this->assertSame(131047, $message->metadata['errors'][0]['code']);
    }

    public function test_concurrent_duplicate_of_same_wamid_persists_single_message_and_single_identity(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;
        $instance = $this->createMetaInstance($tenantId);

        $payload = [
            'provider' => 'meta',
            'event_type' => 'messages',
            'instance_id' => (string) $instance->id,
            'tenant_id' => $tenantId,
            'direction' => 'incoming',
            'message' => [
                'id' => 'wamid.CONCURRENT1',
                'body' => 'Entrega duplicada',
                'type' => 'text',
                'chatid' => '5511955555555@wa.gw',
                'fromMe' => false,
                'timestamp' => '2026-07-21T10:00:00Z',
            ],
        ];

        // Reentrega da Meta — mesma chave (tenant, instance, external_id).
        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);
        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $this->assertSame(
            1,
            ChatMessage::query()->where('external_id', 'wamid.CONCURRENT1')->count(),
            'Duas reentregas do mesmo WAMID devem gerar uma única mensagem',
        );
        $this->assertSame(
            1,
            DB::table('chat_message_identities')
                ->where('tenant_id', $tenantId)
                ->where('instance_id', (string) $instance->id)
                ->where('external_id', 'wamid.CONCURRENT1')
                ->count(),
            'A reserva atômica deve existir uma única vez',
        );
    }

    public function test_same_wamid_on_two_instances_stays_independent(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;
        $firstInstance = $this->createMetaInstance($tenantId);
        $secondInstance = ChatInstance::factory()->create([
            'tenant_id' => $tenantId,
            'provider' => 'meta',
            'webhook_token' => 'meta-webhook-token-2',
            'settings_json' => [
                'phone_number_id' => '0987654321',
                'access_token' => 'secret-token-2',
            ],
        ]);

        $basePayload = [
            'provider' => 'meta',
            'event_type' => 'messages',
            'tenant_id' => $tenantId,
            'direction' => 'incoming',
            'message' => [
                'id' => 'wamid.SHARED1',
                'body' => 'Mesmo WAMID em instâncias diferentes',
                'type' => 'text',
                'chatid' => '5511944444444@wa.gw',
                'fromMe' => false,
                'timestamp' => '2026-07-21T10:00:00Z',
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest(
            $tenantId,
            [...$basePayload, 'instance_id' => (string) $firstInstance->id],
        );
        app(ChatWebhookIngestor::class)->ingest(
            $tenantId,
            [...$basePayload, 'instance_id' => (string) $secondInstance->id],
        );

        $this->assertSame(
            2,
            ChatMessage::query()->where('external_id', 'wamid.SHARED1')->count(),
            'Mesmo WAMID em instâncias distintas não pode colidir',
        );

        $messages = ChatMessage::query()
            ->where('external_id', 'wamid.SHARED1')
            ->with('ticket')
            ->get();
        $instanceIds = $messages
            ->map(fn (ChatMessage $message): ?string => $message->ticket?->instance_id)
            ->filter()
            ->sort()
            ->values()
            ->all();

        $this->assertSame(
            [(string) $firstInstance->id, (string) $secondInstance->id],
            array_values(array_unique($instanceIds)),
            'Cada mensagem deve pertencer à instância que a entregou',
        );

        $this->assertSame(
            2,
            DB::table('chat_message_identities')
                ->where('tenant_id', $tenantId)
                ->where('external_id', 'wamid.SHARED1')
                ->count(),
            'A identidade deve ser única por instância, não por WAMID global',
        );
    }

    public function test_status_update_scopes_by_instance(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;
        $firstInstance = $this->createMetaInstance($tenantId);
        $secondInstance = ChatInstance::factory()->create([
            'tenant_id' => $tenantId,
            'provider' => 'meta',
            'webhook_token' => 'meta-webhook-token-3',
            'settings_json' => [
                'phone_number_id' => '1111111111',
                'access_token' => 'secret-token-3',
            ],
        ]);

        // Mensagem entregue pela instância 1.
        app(ChatWebhookIngestor::class)->ingest($tenantId, [
            'provider' => 'meta',
            'event_type' => 'messages',
            'instance_id' => (string) $firstInstance->id,
            'tenant_id' => $tenantId,
            'direction' => 'incoming',
            'message' => [
                'id' => 'wamid.SCOPED1',
                'body' => 'Oi',
                'type' => 'text',
                'chatid' => '5511933333333@wa.gw',
                'fromMe' => false,
                'timestamp' => '2026-07-21T10:00:00Z',
            ],
        ]);

        // Status da instância 2 NÃO pode atualizar a mensagem da instância 1.
        app(ChatWebhookIngestor::class)->ingest($tenantId, [
            'provider' => 'meta',
            'event_type' => 'status',
            'instance_id' => (string) $secondInstance->id,
            'tenant_id' => $tenantId,
            'status' => [
                'messageId' => 'wamid.SCOPED1',
                'status' => 'read',
            ],
        ]);

        $message = ChatMessage::query()->where('external_id', 'wamid.SCOPED1')->first();
        $this->assertNotNull($message);
        $this->assertNotSame('read', $message->status, 'Status de outra instância não pode alterar a mensagem');

        // Status da instância correta atualiza.
        app(ChatWebhookIngestor::class)->ingest($tenantId, [
            'provider' => 'meta',
            'event_type' => 'status',
            'instance_id' => (string) $firstInstance->id,
            'tenant_id' => $tenantId,
            'status' => [
                'messageId' => 'wamid.SCOPED1',
                'status' => 'read',
            ],
        ]);

        $message->refresh();
        $this->assertSame('read', $message->status);
    }
}
