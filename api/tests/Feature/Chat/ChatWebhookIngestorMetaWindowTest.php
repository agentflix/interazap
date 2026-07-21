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
}
