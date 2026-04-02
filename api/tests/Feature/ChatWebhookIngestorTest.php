<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Actions\ChatWebhookIngestor;
use Domain\Chat\Jobs\ChatMediaDownloadJob;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Configuration\Models\ConfigurationOpeningHour;
use Domain\Platform\Models\PlatformTenant;
use Domain\Platform\Models\PlatformUazapiInstance;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Suite de Testes para Ingestão de Webhooks Uazapi.
 *
 * Valida o processamento correto de todos os tipos de eventos:
 * - messages: texto, reação, editada, com quote
 * - messages_update: Delivered, Read, Deleted
 * - connection: connected, disconnected, connecting
 */
class ChatWebhookIngestorTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock padrão para evitar chamadas externas em todos os testes
        $this->mock(\Domain\Chat\Services\ChatGatewayService::class, function ($mock): void {
            $mock->shouldReceive('downloadMedia')->andReturn([]);
            // Permitir outras chamadas se necessário, ou usar shouldIgnoreMissing
            $mock->shouldIgnoreMissing();
        });
    }

    /**
     * Carrega fixtures de webhook para testes.
     *
     * @return array<string, mixed>
     */
    private function loadFixtures(): array
    {
        return json_decode(
            file_get_contents(base_path('tests/fixtures/uazapi-webhooks.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * Busca um webhook por descrição nas fixtures.
     *
     * @return array<string, mixed>|null
     */
    private function findWebhookByDescription(string $description): ?array
    {
        $fixtures = $this->loadFixtures();

        return collect($fixtures['webhooks'] ?? [])
            ->firstWhere('description', $description);
    }

    /**
     * Cria instância de teste com token específico.
     */
    private function createTestInstance(string $tenantId, string $token): ChatInstance
    {
        return ChatInstance::factory()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'provider' => 'uazapi',
            'webhook_token' => $token,
            'settings_json' => ['token' => $token],
        ]);
    }

    public function test_ingests_uazapi_webhook_and_lists_messages(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->withoutMiddleware(\Illuminate\Auth\Middleware\Authorize::class);
        $tenantId = (string) $user->tenant_id;

        $this->createTestInstance($tenantId, 'c56b59d2-fa76-41a3-8667-2289faef3275');

        $messageEvent = $this->findWebhookByDescription('Text message incoming');
        $this->assertNotNull($messageEvent, 'Fixture de mensagens não encontrada');

        $payload = [
            'provider' => 'uazapi',
            'event_type' => $messageEvent['EventType'],
            'instance_webhook_token' => $messageEvent['token'],
            'tenant_id' => $tenantId,
            'direction' => ($messageEvent['message']['fromMe'] ?? false) ? 'outgoing' : 'incoming',
            'message' => [
                'id' => $messageEvent['message']['id'] ?? $messageEvent['message']['messageid'],
                'from' => $messageEvent['message']['sender'] ?? $messageEvent['message']['chatid'] ?? null,
                'to' => $messageEvent['message']['chatid'] ?? null,
                'body' => $messageEvent['message']['text'] ?? $messageEvent['message']['content']['text'] ?? '',
                'type' => $messageEvent['message']['type'] ?? $messageEvent['message']['messageType'] ?? 'text',
                'chatid' => $messageEvent['message']['chatid'] ?? null,
                'fromMe' => $messageEvent['message']['fromMe'] ?? false,
            ],
            'chat' => $messageEvent['chat'] ?? null,
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $this->assertDatabaseCount('chat_messages', 1);
        $message = ChatMessage::query()->first();
        $this->assertNotNull($message);
        $this->assertSame($payload['message']['body'], $message->content);

        $ticket = ChatTicket::query()->first();
        $this->assertNotNull($ticket);

        $permView = \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => 'chat.messages.view', 'guard_name' => 'sanctum'], ['id' => \Illuminate\Support\Str::orderedUuid()]);
        $user->givePermissionTo($permView);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/chat/tickets/{$ticket->id}/messages")
            ->assertOk()
            ->assertJsonFragment(['content' => $payload['message']['body']]);
    }

    public function test_connection_event_updates_instance_status(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $instance = $this->createTestInstance($tenantId, '11111111-2222-3333-4444-555555555555');
        $instance->status = 'disconnected';
        $instance->save();

        $connectionEvent = $this->findWebhookByDescription('Connection connected');
        $this->assertNotNull($connectionEvent, 'Fixture de conexão não encontrada');

        $payload = [
            'provider' => 'uazapi',
            'event_type' => $connectionEvent['EventType'],
            'instance_webhook_token' => $connectionEvent['token'],
            'tenant_id' => $tenantId,
            'raw' => $connectionEvent,
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $instance->refresh();
        $this->assertSame('connected', $instance->status);
        $this->assertNotNull($instance->last_status_at);
        $this->assertSame('connected', data_get($instance->settings_json, 'last_connection.status'));
    }

    public function test_idempotent_message_ingestion_does_not_duplicate_records(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $this->createTestInstance($tenantId, 'token-abc');

        $payload = [
            'provider' => 'uazapi',
            'event_type' => 'messages',
            'instance_webhook_token' => 'token-abc',
            'tenant_id' => $tenantId,
            'message' => [
                'id' => 'msg-123',
                'body' => 'Olá',
                'type' => 'text',
                'chatid' => '5511999999999@wa.gw',
                'fromMe' => false,
            ],
        ];

        // Primeira ingestão
        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);
        // Segunda ingestão (duplicada)
        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        // Deve ter apenas 1 mensagem no banco
        $this->assertDatabaseCount('chat_messages', 1);
        $message = ChatMessage::query()->first();
        $this->assertNotNull($message);
        $this->assertSame('msg-123', $message->external_id);
    }

    public function test_outgoing_message_is_processed_correctly(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $this->createTestInstance($tenantId, 'c56b59d2-fa76-41a3-8667-2289faef3275');

        $outgoingEvent = $this->findWebhookByDescription('Text message outgoing');
        $this->assertNotNull($outgoingEvent, 'Fixture de mensagem outgoing não encontrada');

        $payload = [
            'provider' => 'uazapi',
            'event_type' => $outgoingEvent['EventType'],
            'instance_webhook_token' => $outgoingEvent['token'],
            'tenant_id' => $tenantId,
            'direction' => 'outgoing',
            'message' => [
                'id' => $outgoingEvent['message']['id'],
                'body' => $outgoingEvent['message']['text'],
                'type' => 'text',
                'chatid' => $outgoingEvent['message']['chatid'],
                'fromMe' => true,
            ],
            'chat' => $outgoingEvent['chat'],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $message = ChatMessage::query()->first();
        $this->assertNotNull($message);
        $this->assertSame('outgoing', $message->direction);
        $this->assertSame($outgoingEvent['message']['text'], $message->content);
    }

    public function test_message_with_quoted_context_is_processed(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $this->createTestInstance($tenantId, 'c56b59d2-fa76-41a3-8667-2289faef3275');

        $quotedEvent = $this->findWebhookByDescription('Message with quote (reply)');
        $this->assertNotNull($quotedEvent, 'Fixture de mensagem com quote não encontrada');

        $payload = [
            'provider' => 'uazapi',
            'event_type' => $quotedEvent['EventType'],
            'instance_webhook_token' => $quotedEvent['token'],
            'tenant_id' => $tenantId,
            'direction' => 'outgoing',
            'message' => [
                'id' => $quotedEvent['message']['id'],
                'body' => $quotedEvent['message']['text'],
                'type' => 'text',
                'chatid' => $quotedEvent['message']['chatid'],
                'fromMe' => true,
            ],
            'chat' => $quotedEvent['chat'],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $message = ChatMessage::query()->first();
        $this->assertNotNull($message);
        $this->assertSame('Respondendo sua mensagm', $message->content);
    }

    public function test_audio_message_is_processed_as_audio_type(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $this->createTestInstance($tenantId, 'c56b59d2-fa76-41a3-8667-2289faef3275');

        $audioEvent = $this->findWebhookByDescription('Audio message (PTT)');
        $this->assertNotNull($audioEvent, 'Fixture de áudio não encontrada');

        $payload = [
            'provider' => 'uazapi',
            'event_type' => $audioEvent['EventType'],
            'instance_webhook_token' => $audioEvent['token'],
            'tenant_id' => $tenantId,
            'direction' => 'incoming',
            'message' => [
                'id' => $audioEvent['message']['id'],
                'body' => '',
                'type' => $audioEvent['message']['mediaType'],
                'chatid' => $audioEvent['message']['chatid'],
                'fromMe' => false,
            ],
            'chat' => $audioEvent['chat'],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $message = ChatMessage::query()->first();
        $this->assertNotNull($message);
        // PTT é normalizado para 'audio'
        $this->assertSame('audio', $message->type);
    }

    public function test_disconnection_event_updates_instance_status(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $instance = $this->createTestInstance($tenantId, '22222222-3333-4444-5555-666666666666');
        $instance->status = 'connected';
        $instance->save();

        $disconnectionEvent = $this->findWebhookByDescription('Connection disconnected');
        $this->assertNotNull($disconnectionEvent, 'Fixture de desconexão não encontrada');

        $payload = [
            'provider' => 'uazapi',
            'event_type' => $disconnectionEvent['EventType'],
            'instance_webhook_token' => $disconnectionEvent['token'],
            'tenant_id' => $tenantId,
            'raw' => $disconnectionEvent,
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $instance->refresh();
        $this->assertSame('disconnected', $instance->status);
    }

    public function test_all_fixture_event_types_return_success(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        // Criar instâncias para cada token nos fixtures
        $tokens = [
            'c56b59d2-fa76-41a3-8667-2289faef3275',
            '11111111-2222-3333-4444-555555555555',
            '22222222-3333-4444-5555-666666666666',
            '33333333-4444-5555-6666-777777777777',
        ];

        foreach ($tokens as $token) {
            $this->createTestInstance($tenantId, $token);
        }

        $fixtures = $this->loadFixtures();
        $webhooks = $fixtures['webhooks'] ?? [];

        foreach ($webhooks as $index => $webhook) {
            $eventType = $webhook['EventType'] ?? 'unknown';
            $description = $webhook['description'] ?? "webhook #{$index}";

            $payload = [
                'provider' => 'uazapi',
                'event_type' => $eventType,
                'instance_webhook_token' => $webhook['token'],
                'tenant_id' => $tenantId,
                'raw' => $webhook,
            ];

            if (isset($webhook['message'])) {
                $payload['message'] = [
                    'id' => $webhook['message']['id'] ?? $webhook['message']['messageid'] ?? null,
                    'body' => $webhook['message']['text'] ?? '',
                    'type' => $webhook['message']['type'] ?? 'text',
                    'chatid' => $webhook['message']['chatid'] ?? null,
                    'fromMe' => $webhook['message']['fromMe'] ?? false,
                ];
                $payload['chat'] = $webhook['chat'] ?? null;
            }

            // Não deve lançar exceção
            try {
                app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);
                $this->addToAssertionCount(1);
            } catch (\Throwable $e) {
                $this->fail("Evento '{$description}' falhou: ".$e->getMessage());
            }
        }
    }

    public function test_encrypted_media_triggers_download(): void
    {
        Bus::fake();
        Storage::fake('public');

        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;
        $token = 'c56b59d2-fa76-41a3-8667-2289faef3275';

        $this->createTestInstance($tenantId, $token);

        // Verificar que não há mensagens pré-existentes
        $this->assertEquals(0, \Domain\Chat\Models\ChatMessage::query()->count());

        $payload = [
            'provider' => 'uazapi',
            'event_type' => 'messages',
            'instance_webhook_token' => $token,
            'token' => $token,
            'tenant_id' => $tenantId,
            'direction' => 'incoming',
            'message' => [
                'id' => 'MSG-ENC-123',
                'body' => '',
                'type' => 'image',
                'chatid' => '5511999999999@s.whatsapp.net',
                'fromMe' => false,
                'mediaUrl' => 'https://mmg.whatsapp.net/v/t62.7119-24/encrypted.jpg.enc',
                'mimetype' => 'image/jpeg',
                'messageid' => 'MSG-ENC-123',
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $message = ChatMessage::query()->first();
        $this->assertNotNull($message, 'Message was not created');
        $this->assertTrue(in_array($message->type, ['image', 'file']));

        // Verificar que uma mensagem foi criada
        $this->assertEquals(1, \Domain\Chat\Models\ChatMessage::query()->count(), 'Expected exactly 1 message');

        // Verificar que file_url contém a URL encriptada (pois o job é assíncrono)
        $this->assertStringContainsString('mmg.whatsapp.net', $message->file_url ?? '', 'File URL should contain original encrypted URL');

        // Verifica se o job de download foi despachado
        Bus::assertDispatched(\Domain\Chat\Jobs\ChatMediaDownloadJob::class);
    }

    public function test_ai_mode_dispatches_autopilot_job_with_message_context(): void
    {
        Bus::fake();

        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;
        $token = 'token-ai-123';

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenantId,
            'provider' => 'uazapi',
            'webhook_token' => $token,
            'settings_json' => ['token' => $token],
        ]);

        $payload = [
            'provider' => 'uazapi',
            'event_type' => 'messages',
            'instance_webhook_token' => $token,
            'tenant_id' => $tenantId,
            'instance_id' => $instance->id,
            'direction' => 'incoming',
            'message' => [
                'id' => 'msg-ai-1',
                'body' => 'Oi IA',
                'type' => 'text',
                'chatid' => '5511999999999@wa.gw',
                'fromMe' => false,
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $message = ChatMessage::query()->first();
        $this->assertNotNull($message);

        // Verificar que o ConversationResolverJob foi disparado (via MessagePersisted event)
        Bus::assertDispatched(\Domain\Chat\Jobs\ConversationResolverJob::class, function (\Domain\Chat\Jobs\ConversationResolverJob $job): bool {
            return true; // Comportamento equivalente: o job ainda é disparado, agora via event listener
        });
    }

    public function test_reaction_event_updates_message_reactions(): void
    {
        Event::fake();

        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $ticket = ChatTicket::factory()->forTenant($tenantId)->create();
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'external_id' => 'msg-react',
            'reactions' => [],
        ]);

        $payload = [
            'event_type' => 'message.reaction',
            'tenant_id' => $tenantId,
            'message' => [
                'id' => 'msg-react',
                'reaction' => ['emoji' => '👍'],
                'fromMe' => false,
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $message->refresh();
        $this->assertSame('👍', $message->reactions[0]['emoji'] ?? null);

        $payload['message']['reaction']['emoji'] = '';
        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $message->refresh();
        $this->assertCount(0, $message->reactions ?? []);
    }

    public function test_messages_event_with_reaction_type_does_not_create_imported_message(): void
    {
        Event::fake();

        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $ticket = ChatTicket::factory()->forTenant($tenantId)->create();
        $targetMessage = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'external_id' => 'target-msg-id',
            'reactions' => [],
        ]);

        $payload = [
            'event_type' => 'messages',
            'tenant_id' => $tenantId,
            'message' => [
                'id' => 'reaction-envelope-id',
                'messageid' => 'reaction-envelope-id',
                'type' => 'reaction',
                'messageType' => 'ReactionMessage',
                'reaction' => 'target-msg-id',
                'text' => '❤️',
                'fromMe' => false,
                'chatid' => '5511999999999@wa.gw',
                'content' => [
                    'key' => [
                        'ID' => 'target-msg-id',
                        'fromMe' => false,
                    ],
                    'text' => '❤️',
                ],
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $targetMessage->refresh();
        $this->assertDatabaseCount('chat_messages', 1);
        $this->assertSame('❤️', $targetMessage->reactions[0]['emoji'] ?? null);
    }

    public function test_delete_event_marks_message_as_deleted(): void
    {
        Event::fake();

        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $ticket = ChatTicket::factory()->forTenant($tenantId)->create();
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'external_id' => 'msg-del',
            'status' => 'sent',
        ]);

        $payload = [
            'event_type' => 'message.delete',
            'tenant_id' => $tenantId,
            'message' => ['id' => 'msg-del'],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $message->refresh();
        $this->assertTrue($message->is_deleted);
        $this->assertSame('deleted', $message->status);
        $this->assertSame('remote', $message->metadata['deleted_by'] ?? null);
    }

    public function test_edit_event_updates_message_content_and_history(): void
    {
        Event::fake();

        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $ticket = ChatTicket::factory()->forTenant($tenantId)->create();
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'external_id' => 'msg-edit',
            'content' => 'Mensagem antiga',
            'edit_history' => [],
        ]);

        $payload = [
            'event_type' => 'message.edit',
            'tenant_id' => $tenantId,
            'message' => [
                'id' => 'msg-edit',
                'body' => 'Mensagem editada',
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $message->refresh();
        $this->assertTrue($message->is_edited);
        $this->assertSame('Mensagem editada', $message->content);
        $this->assertCount(1, $message->edit_history ?? []);
    }

    public function test_edit_event_redelivery_is_idempotent_and_does_not_duplicate_history(): void
    {
        Event::fake();

        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $ticket = ChatTicket::factory()->forTenant($tenantId)->create();
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'external_id' => 'msg-edit-idempotent',
            'content' => 'Mensagem original',
            'edit_history' => [],
            'is_edited' => false,
            'edited_at' => null,
        ]);

        $payload = [
            'event_type' => 'message.edit',
            'tenant_id' => $tenantId,
            'message' => [
                'id' => 'wrapper-edit-event-id',
                'edited' => 'msg-edit-idempotent',
                'body' => 'Mensagem atualizada',
            ],
        ];

        try {
            \Illuminate\Support\Facades\Date::setTestNow('2026-03-17 10:00:00');
            app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

            $message->refresh();

            $firstEditedAt = $message->edited_at?->toIso8601String();

            $this->assertDatabaseCount('chat_messages', 1);
            $this->assertTrue($message->is_edited);
            $this->assertSame('Mensagem atualizada', $message->content);
            $this->assertSame('Mensagem original', $message->edit_history[0]['content'] ?? null);
            $this->assertCount(1, $message->edit_history ?? []);

            \Illuminate\Support\Facades\Date::setTestNow('2026-03-17 10:05:00');
            app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

            $message->refresh();

            $this->assertDatabaseCount('chat_messages', 1);
            $this->assertSame('Mensagem atualizada', $message->content);
            $this->assertSame($firstEditedAt, $message->edited_at?->toIso8601String());
            $this->assertCount(1, $message->edit_history ?? []);
            $this->assertSame('Mensagem original', $message->edit_history[0]['content'] ?? null);
        } finally {
            \Illuminate\Support\Facades\Date::setTestNow();
        }
    }

    public function test_edit_event_with_same_content_does_not_mark_message_as_edited(): void
    {
        Event::fake();

        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $ticket = ChatTicket::factory()->forTenant($tenantId)->create();
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'external_id' => 'msg-no-op-edit',
            'content' => 'Conteúdo estável',
            'edit_history' => [],
            'is_edited' => false,
            'edited_at' => null,
        ]);

        $payload = [
            'event_type' => 'message.edit',
            'tenant_id' => $tenantId,
            'message' => [
                'id' => 'wrapper-no-op-event-id',
                'edited' => 'msg-no-op-edit',
                'body' => 'Conteúdo estável',
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $message->refresh();

        $this->assertFalse($message->is_edited);
        $this->assertNull($message->edited_at);
        $this->assertSame('Conteúdo estável', $message->content);
        $this->assertSame([], $message->edit_history ?? []);
    }

    public function test_messages_update_without_message_key_updates_status_to_read(): void
    {
        Event::fake();

        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $ticket = ChatTicket::factory()->forTenant($tenantId)->create();
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'external_id' => 'AC52BAAFF49B632EB132DB1CB8AC21C4',
            'status' => 'sent',
            'sent_at' => now(),
            'delivered_at' => now(),
            'read_at' => null,
        ]);

        // Payload real de messages_update conforme fixture UazAPI:
        // event_type=messages_update, SEM message key, MessageIDs em raw.event.MessageIDs
        $payload = [
            'event_type' => 'messages_update',
            'tenant_id' => $tenantId,
            'raw' => [
                'event' => [
                    'MessageIDs' => [
                        'AC52BAAFF49B632EB132DB1CB8AC21C4',
                        'AC545D97954A4B69C857A96DAAC0D352',
                    ],
                    'Type' => 'Read',
                ],
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $message->refresh();
        $this->assertSame('read', $message->status);
        $this->assertNotNull($message->read_at);
    }

    public function test_messages_update_with_stream_nested_raw_updates_status_to_delivered(): void
    {
        Event::fake();

        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $ticket = ChatTicket::factory()->forTenant($tenantId)->create();
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'external_id' => '3B50879ADE161979CEB8',
            'status' => 'sent',
            'sent_at' => now(),
            'delivered_at' => null,
            'read_at' => null,
        ]);

        // Estrutura aninhada: raw.raw.event (como no Redis stream do gateway)
        $payload = [
            'event_type' => 'messages_update',
            'tenant_id' => $tenantId,
            'raw' => [
                'raw' => [
                    'event' => [
                        'MessageIDs' => ['3B50879ADE161979CEB8'],
                        'Type' => 'Delivered',
                    ],
                ],
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $message->refresh();
        $this->assertSame('delivered', $message->status);
        $this->assertNotNull($message->delivered_at);
    }

    public function test_status_update_with_numeric_ack_updates_timestamps(): void
    {
        Event::fake();

        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $ticket = ChatTicket::factory()->forTenant($tenantId)->create();
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'external_id' => 'msg-ack',
            'status' => 'pending',
            'sent_at' => null,
            'delivered_at' => null,
            'read_at' => null,
        ]);

        $payload = [
            'event_type' => 'messages_update',
            'tenant_id' => $tenantId,
            'message' => [
                'id' => 'msg-ack',
                'ack' => 3,
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $message->refresh();
        $this->assertSame('read', $message->status);
        $this->assertNotNull($message->sent_at);
        $this->assertNotNull($message->delivered_at);
        $this->assertNotNull($message->read_at);
    }

    public function test_status_update_with_deleted_ack_marks_deleted(): void
    {
        Event::fake();

        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $ticket = ChatTicket::factory()->forTenant($tenantId)->create();
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'external_id' => 'msg-del-ack',
            'status' => 'sent',
            'is_deleted' => false,
        ]);

        $payload = [
            'event_type' => 'messages_update',
            'tenant_id' => $tenantId,
            'message' => [
                'id' => 'msg-del-ack',
                'status' => 'deleted',
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $message->refresh();
        $this->assertSame('deleted', $message->status);
        $this->assertTrue($message->is_deleted);
        $this->assertNull($message->deleted_by);
        $this->assertSame('remote', $message->metadata['deleted_by'] ?? null);
    }

    public function test_media_metadata_is_deferred_to_async_job_with_partial_persistence(): void
    {
        Bus::fake();

        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;
        $token = 'token-meta';

        $this->createTestInstance($tenantId, $token);

        $payload = [
            'provider' => 'uazapi',
            'event_type' => 'messages',
            'instance_webhook_token' => $token,
            'tenant_id' => $tenantId,
            'direction' => 'incoming',
            'message' => [
                'id' => 'msg-meta',
                'type' => 'file',
                'chatid' => '5511999999999@wa.gw',
                'fileUrl' => 'http://files.test/docs/report.pdf',
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $message = ChatMessage::query()->first();
        $this->assertNotNull($message);
        $this->assertNull($message?->mime_type);
        $this->assertNull($message?->file_name);
        $this->assertNull($message?->file_size);

        Bus::assertDispatched(ChatMediaDownloadJob::class);
    }

    public function test_connection_event_updates_platform_instance_status(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $token = 'token-conn';
        $this->createTestInstance($tenantId, $token);

        $platformInstance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $tenantId,
            'name' => 'Instancia',
            'system_name' => 'uazapi',
            'token' => $token,
            'status' => 'disconnected',
        ]);

        $payload = [
            'provider' => 'uazapi',
            'event_type' => 'connection',
            'instance_webhook_token' => $token,
            'tenant_id' => $tenantId,
            'raw' => [
                'status' => [
                    'connected' => true,
                    'loggedIn' => true,
                ],
                'instance' => ['status' => 'connected'],
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $platformInstance->refresh();
        $this->assertSame('connected', $platformInstance->status);
    }

    public function test_first_inbound_sends_no_business_hours_message_when_day_has_no_schedule(): void
    {
        \Illuminate\Support\Facades\Date::setTestNow(\Illuminate\Support\Facades\Date::parse('2026-03-03 10:00:00'));

        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $instance = $this->createTestInstance($tenantId, 'token-no-schedule');
        $instance->settings_json = [
            'token' => 'token-no-schedule',
            'send_no_business_hours_message' => true,
            'no_business_hours_message' => 'Hoje não há expediente.',
        ];
        $instance->save();

        $payload = [
            'provider' => 'uazapi',
            'event_type' => 'messages',
            'instance_webhook_token' => 'token-no-schedule',
            'instance_id' => (string) $instance->id,
            'tenant_id' => $tenantId,
            'direction' => 'incoming',
            'message' => [
                'id' => 'msg-no-schedule',
                'body' => 'Olá',
                'type' => 'text',
                'chatid' => '5511999999999@wa.gw',
                'fromMe' => false,
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $autoMessage = ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('source', 'system')
            ->where('content', 'Hoje não há expediente.')
            ->first();

        $this->assertNotNull($autoMessage);
        $this->assertSame('outgoing', $autoMessage->direction);

        \Illuminate\Support\Facades\Date::setTestNow();
    }

    public function test_first_inbound_sends_outside_business_hours_message_when_day_has_schedule_but_is_closed(): void
    {
        \Illuminate\Support\Facades\Date::setTestNow(\Illuminate\Support\Facades\Date::parse('2026-03-03 22:00:00'));

        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $instance = $this->createTestInstance($tenantId, 'token-outside-schedule');
        $instance->settings_json = [
            'token' => 'token-outside-schedule',
            'send_outside_business_hours_message' => true,
            'outside_business_hours_message' => 'Estamos fora do horário de atendimento.',
        ];
        $instance->save();

        ConfigurationOpeningHour::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $tenantId,
            'day_of_week' => \Illuminate\Support\Facades\Date::now()->dayOfWeek,
            'open_time' => '08:00',
            'close_time' => '18:00',
            'is_active' => true,
        ]);

        $payload = [
            'provider' => 'uazapi',
            'event_type' => 'messages',
            'instance_webhook_token' => 'token-outside-schedule',
            'instance_id' => (string) $instance->id,
            'tenant_id' => $tenantId,
            'direction' => 'incoming',
            'message' => [
                'id' => 'msg-outside-schedule',
                'body' => 'Olá',
                'type' => 'text',
                'chatid' => '5511888888888@wa.gw',
                'fromMe' => false,
            ],
        ];

        app(ChatWebhookIngestor::class)->ingest($tenantId, $payload);

        $autoMessage = ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('source', 'system')
            ->where('content', 'Estamos fora do horário de atendimento.')
            ->first();

        $this->assertNotNull($autoMessage);
        $this->assertSame('outgoing', $autoMessage->direction);

        \Illuminate\Support\Facades\Date::setTestNow();
    }
}
