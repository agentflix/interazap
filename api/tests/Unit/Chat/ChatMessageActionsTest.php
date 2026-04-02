<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Actions\ChatMessageActions;
use Domain\Chat\Actions\ChatTicketActions;
use Domain\Chat\DTOs\ChatMessageDTO;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatActivityBroadcastService;
use Domain\Chat\Services\ChatBroadcastService;
use Domain\Chat\Services\ChatGatewayService;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Services\GatewayBroadcastService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class ChatMessageActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_to_gateway_marks_failed_when_missing_token_or_number(): void
    {
        Http::fake();
        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldIgnoreMissing();
        $activityBroadcast = $this->makeActivityBroadcast();
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);

        Event::fake();

        $actions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'phone' => null,
            'phone_e164' => null,
            'remote_jid' => null,
        ]);

        $dto = ChatMessageDTO::fromArray([
            'ticket_id' => $ticket->id,
            'content' => 'Olá',
            'direction' => 'outgoing',
            'type' => 'text',
            'source' => ChatMessageDTO::SOURCE_AGENT,
        ]);

        $message = $actions->create($tenant->id, $dto);

        $this->assertSame('failed', $message->status);
        $this->assertSame('Instance token or destination missing', $message->error_message);
    }

    public function test_send_text_message_successfully(): void
    {
        Http::fake();
        $gateway = Mockery::mock(ChatGatewayService::class);
        $activityBroadcast = $this->makeActivityBroadcast();
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);

        $gateway->shouldReceive('sendText')
            ->once()
            ->andReturn(['messageid' => 'msg-1']);

        Event::fake();

        $actions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-1',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'phone' => '5511999999999',
            'instance_id' => $instance->id,
        ]);

        $dto = ChatMessageDTO::fromArray([
            'ticket_id' => $ticket->id,
            'content' => 'Mensagem',
            'direction' => 'outgoing',
            'type' => 'text',
            'source' => ChatMessageDTO::SOURCE_AGENT,
        ]);

        $message = $actions->create($tenant->id, $dto);

        $this->assertSame('sent', $message->status);
        $this->assertSame('msg-1', $message->external_id);
    }

    public function test_send_text_message_prefixes_attendant_name_when_integration_flag_is_enabled(): void
    {
        Http::fake();
        $gateway = Mockery::mock(ChatGatewayService::class);
        $activityBroadcast = $this->makeActivityBroadcast();
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);

        $gateway->shouldReceive('sendText')
            ->once()
            ->withArgs(function (string $token, array $payload): bool {
                $this->assertSame('tok-1a', $token);
                $this->assertSame("Rafael Silva:\nMinha mensagem", $payload['text'] ?? null);

                return true;
            })
            ->andReturn(['messageid' => 'msg-1a']);

        Event::fake();

        $actions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $tenant = PlatformTenant::factory()->create();
        $user = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Rafael Silva',
        ]);
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-1a',
            'settings_json' => [
                'send_attendant_name' => true,
            ],
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'phone' => '5511999999999',
            'instance_id' => $instance->id,
        ]);

        $dto = ChatMessageDTO::fromArray([
            'ticket_id' => $ticket->id,
            'content' => 'Minha mensagem',
            'direction' => 'outgoing',
            'type' => 'text',
            'source' => ChatMessageDTO::SOURCE_AGENT,
            'user_id' => (string) $user->id,
        ]);

        $message = $actions->create($tenant->id, $dto);

        $this->assertSame('sent', $message->status);
        $this->assertSame('msg-1a', $message->external_id);
    }

    public function test_send_text_message_prefixes_ai_agent_name(): void
    {
        Http::fake();
        $gateway = Mockery::mock(ChatGatewayService::class);
        $activityBroadcast = $this->makeActivityBroadcast();
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);

        $gateway->shouldReceive('sendText')
            ->once()
            ->withArgs(function (string $token, array $payload): bool {
                $this->assertSame('tok-ai', $token);
                $this->assertSame("Suporte Inteligente:\nOlá, tudo bem?", $payload['text'] ?? null);

                return true;
            })
            ->andReturn(['messageid' => 'msg-ai']);

        Event::fake();

        $actions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-ai',
            'settings_json' => [
                'send_attendant_name' => true,
            ],
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'phone' => '5511999999999',
            'instance_id' => $instance->id,
        ]);

        $dto = ChatMessageDTO::fromArray([
            'ticket_id' => $ticket->id,
            'content' => 'Olá, tudo bem?',
            'direction' => 'outgoing',
            'type' => 'text',
            'source' => 'ai',
            'metadata' => ['ai_agent_name' => 'Suporte Inteligente'],
        ]);

        $message = $actions->create($tenant->id, $dto);

        $this->assertSame('sent', $message->status);
        $this->assertSame('msg-ai', $message->external_id);
    }

    public function test_send_contact_message_successfully(): void
    {
        Http::fake();
        $gateway = Mockery::mock(ChatGatewayService::class);
        $activityBroadcast = $this->makeActivityBroadcast();
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);

        $gateway->shouldReceive('sendContact')
            ->once()
            ->andReturn(['messageid' => 'contact-1']);

        Event::fake();

        $actions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-2',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'phone' => '5511999999999',
            'instance_id' => $instance->id,
        ]);

        $dto = ChatMessageDTO::fromArray([
            'ticket_id' => $ticket->id,
            'content' => '',
            'direction' => 'outgoing',
            'type' => 'contact',
            'source' => ChatMessageDTO::SOURCE_AGENT,
            'metadata' => [
                'contact' => [
                    'fullName' => 'Contato',
                    'phoneNumber' => '5511',
                    'organization' => 'InteraZap',
                ],
            ],
        ]);

        $message = $actions->sendContact($tenant->id, $dto);

        $this->assertSame('sent', $message->status);
        $this->assertSame('contact-1', $message->external_id);
    }

    public function test_send_location_message_successfully(): void
    {
        Http::fake();
        $gateway = Mockery::mock(ChatGatewayService::class);
        $activityBroadcast = $this->makeActivityBroadcast();
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);

        $gateway->shouldReceive('sendLocation')
            ->once()
            ->andReturn(['messageid' => 'loc-1']);

        Event::fake();

        $actions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-3',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'phone' => '5511999999999',
            'instance_id' => $instance->id,
        ]);

        $dto = ChatMessageDTO::fromArray([
            'ticket_id' => $ticket->id,
            'content' => 'Localização',
            'direction' => 'outgoing',
            'type' => 'location',
            'source' => ChatMessageDTO::SOURCE_AGENT,
            'metadata' => [
                'location' => [
                    'latitude' => -23.0,
                    'longitude' => -46.0,
                    'name' => 'InteraZap HQ',
                ],
            ],
        ]);

        $message = $actions->sendLocation($tenant->id, $dto);

        $this->assertSame('sent', $message->status);
        $this->assertSame('loc-1', $message->external_id);
    }

    public function test_send_file_message_uses_base64_for_local_storage(): void
    {
        Storage::fake('public');
        config()->set('app.url', 'http://app.test');

        Http::fake();
        $gateway = Mockery::mock(ChatGatewayService::class);
        $activityBroadcast = $this->makeActivityBroadcast();
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);

        $gateway->shouldReceive('sendFile')
            ->once()
            ->withArgs(function ($token, array $payload): bool {
                $this->assertSame('tok-4', $token);
                $this->assertSame('5511999999999', $payload['number']);
                $this->assertSame('Imagem', $payload['caption']);
                $this->assertSame('image', $payload['type']);
                $this->assertStringStartsWith('data:image/png;base64,', $payload['file']);

                return true;
            })
            ->andReturn(['messageid' => 'file-1']);

        Event::fake();

        $actions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-4',
        ]);

        Storage::disk('public')->put('chat/media/'.$tenant->id.'/file.png', 'binary');

        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'phone' => '5511999999999',
            'instance_id' => $instance->id,
        ]);

        $dto = ChatMessageDTO::fromArray([
            'ticket_id' => $ticket->id,
            'content' => '',
            'direction' => 'outgoing',
            'type' => 'image',
            'source' => ChatMessageDTO::SOURCE_AGENT,
            'file_url' => 'http://app.test/storage/chat/media/'.$tenant->id.'/file.png',
        ]);

        $message = $actions->create($tenant->id, $dto);

        $this->assertSame('sent', $message->status);
        $this->assertSame('file-1', $message->external_id);
    }

    public function test_send_template_message_uses_gateway_template_payload(): void
    {
        Http::fake();
        $gateway = Mockery::mock(ChatGatewayService::class);
        $activityBroadcast = $this->makeActivityBroadcast();
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);

        $gateway->shouldReceive('sendTemplate')
            ->once()
            ->withArgs(function ($token, array $payload): bool {
                $this->assertSame('tok-5', $token);
                $this->assertSame('5511999999999', $payload['number']);
                $this->assertSame('welcome', $payload['templateId']);
                $this->assertSame('pt_BR', $payload['language']);

                return true;
            })
            ->andReturn(['messageid' => 'tpl-1']);

        Event::fake();

        $actions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-5',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'phone' => '5511999999999',
            'instance_id' => $instance->id,
        ]);

        $dto = ChatMessageDTO::fromArray([
            'ticket_id' => $ticket->id,
            'content' => 'Template',
            'direction' => 'outgoing',
            'type' => 'template',
            'source' => ChatMessageDTO::SOURCE_AGENT,
            'metadata' => [
                'template' => [
                    'name' => 'welcome',
                    'language' => ['code' => 'pt_BR'],
                    'components' => [],
                ],
            ],
        ]);

        $message = $actions->create($tenant->id, $dto);

        $this->assertSame('sent', $message->status);
        $this->assertSame('tpl-1', $message->external_id);
    }

    public function test_send_message_marks_failed_when_number_invalid(): void
    {
        Http::fake();
        $gateway = Mockery::mock(ChatGatewayService::class);
        $activityBroadcast = $this->makeActivityBroadcast();
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);

        Event::fake();

        $actions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-6',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'phone' => 'invalid-number',
            'instance_id' => $instance->id,
        ]);

        $dto = ChatMessageDTO::fromArray([
            'ticket_id' => $ticket->id,
            'content' => 'Mensagem',
            'direction' => 'outgoing',
            'type' => 'text',
            'source' => ChatMessageDTO::SOURCE_AGENT,
        ]);

        $message = $actions->create($tenant->id, $dto);

        $this->assertSame('failed', $message->status);
        $this->assertSame('Número destino inválido', $message->error_message);
    }

    public function test_list_by_ticket_filters_by_direction_and_tenant(): void
    {
        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldIgnoreMissing();
        $activityBroadcast = $this->makeActivityBroadcast();
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);

        $actions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $tenant = PlatformTenant::factory()->create();
        $otherTenant = PlatformTenant::factory()->create();

        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create();
        $otherTicket = ChatTicket::factory()->forTenant($otherTenant->id)->create();

        ChatMessage::factory()->count(2)->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'direction' => 'incoming',
        ]);

        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'direction' => 'outgoing',
        ]);

        ChatMessage::factory()->create([
            'tenant_id' => $otherTenant->id,
            'ticket_id' => $otherTicket->id,
            'direction' => 'incoming',
        ]);

        $result = $actions->listByTicket((string) $tenant->id, (string) $ticket->id, ['direction' => 'incoming']);

        $this->assertSame(2, $result->total());
        $this->assertNotEmpty($result->items());
        $this->assertTrue($result->every(fn ($message): bool => $message->direction === 'incoming'));
    }

    public function test_create_updates_ticket_timestamp_and_activates_human_takeover(): void
    {
        Http::fake();
        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldIgnoreMissing();
        $gateway->shouldReceive('sendText')->once()->andReturn(['messageid' => 'tenant-msg-1']);

        $activityBroadcast = $this->makeActivityBroadcast();
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);

        $actions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $tenant = PlatformTenant::factory()->create();
        $user = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-tenant',
        ]);

        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'instance_id' => $instance->id,
            'phone' => '5511999999999',
            'last_message_at' => null,
        ]);

        $dto = ChatMessageDTO::fromArray([
            'ticket_id' => $ticket->id,
            'content' => 'Hello from agent',
            'direction' => 'outgoing',
            'type' => 'text',
            'source' => ChatMessageDTO::SOURCE_AGENT,
            'user_id' => (string) $user->id,
        ]);

        $message = $actions->create((string) $tenant->id, $dto);

        $ticket->refresh();

        $this->assertSame('sent', $message->status);
        $this->assertNotNull($ticket->last_message_at);
        $this->assertNotNull($ticket->human_takeover_at);
    }

    public function test_list_by_ticket_returns_empty_for_other_tenant(): void
    {
        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldIgnoreMissing();
        $activityBroadcast = $this->makeActivityBroadcast();
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);

        $actions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $tenant = PlatformTenant::factory()->create();
        $otherTenant = PlatformTenant::factory()->create();

        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create();

        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
        ]);

        $result = $actions->listByTicket((string) $otherTenant->id, (string) $ticket->id);

        $this->assertSame(0, $result->total());
    }

    public function test_send_text_message_does_not_use_quoted_message_from_other_tenant(): void
    {
        Http::fake();
        $gateway = Mockery::mock(ChatGatewayService::class);
        $activityBroadcast = $this->makeActivityBroadcast();
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);

        $gateway->shouldReceive('sendText')
            ->once()
            ->withArgs(function (string $token, array $payload): bool {
                $this->assertSame('tok-quoted', $token);
                $this->assertSame('Mensagem com quote', $payload['text'] ?? null);
                $this->assertArrayNotHasKey('replyid', $payload);

                return true;
            })
            ->andReturn(['messageid' => 'msg-quoted-1']);

        Event::fake();

        $actions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $tenant = PlatformTenant::factory()->create();
        $otherTenant = PlatformTenant::factory()->create();

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-quoted',
        ]);

        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'phone' => '5511999999999',
            'instance_id' => $instance->id,
        ]);

        $otherTicket = ChatTicket::factory()->forTenant($otherTenant->id)->create();

        $quotedFromOtherTenant = ChatMessage::factory()->create([
            'tenant_id' => $otherTenant->id,
            'ticket_id' => $otherTicket->id,
            'external_id' => 'ext-other-tenant',
            'direction' => 'incoming',
            'type' => 'text',
            'content' => 'Origem de outro tenant',
        ]);

        $dto = ChatMessageDTO::fromArray([
            'ticket_id' => $ticket->id,
            'content' => 'Mensagem com quote',
            'direction' => 'outgoing',
            'type' => 'text',
            'source' => ChatMessageDTO::SOURCE_AGENT,
            'metadata' => [
                'quoted_message_id' => (string) $quotedFromOtherTenant->id,
            ],
        ]);

        $message = $actions->create((string) $tenant->id, $dto);

        $this->assertSame('sent', $message->status);
        $this->assertSame('msg-quoted-1', $message->external_id);
    }

    public function test_prefetch_quoted_messages_respects_tenant_scope(): void
    {
        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldIgnoreMissing();
        $activityBroadcast = $this->makeActivityBroadcast();
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);

        $actions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $tenant = PlatformTenant::factory()->create();
        $otherTenant = PlatformTenant::factory()->create();

        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create();
        $otherTicket = ChatTicket::factory()->forTenant($otherTenant->id)->create();

        ChatMessage::factory()->create([
            'tenant_id' => $otherTenant->id,
            'ticket_id' => $otherTicket->id,
            'external_id' => 'quoted-cross-tenant',
            'direction' => 'incoming',
            'type' => 'text',
            'content' => 'Quoted de outro tenant',
        ]);

        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'direction' => 'incoming',
            'type' => 'text',
            'metadata' => [
                'message' => [
                    'quoted' => 'quoted-cross-tenant',
                ],
            ],
        ]);

        $quotedMap = $actions->prefetchQuotedMessages(collect([$message]));

        $this->assertArrayNotHasKey('quoted-cross-tenant', $quotedMap);
    }

    public function test_internal_note_does_not_send_message_to_gateway(): void
    {
        Http::fake();
        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldNotReceive('sendText');
        $gateway->shouldNotReceive('sendFile');
        $gateway->shouldNotReceive('sendContact');
        $gateway->shouldNotReceive('sendOutboundMessage');
        $gateway->shouldNotReceive('sendTemplate');

        $activityBroadcast = $this->makeActivityBroadcast();
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);
        $actions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create();

        $dto = ChatMessageDTO::fromArray([
            'ticket_id' => (string) $ticket->id,
            'content' => 'Nota interna de transferência',
            'direction' => 'outgoing',
            'type' => 'internal_note',
            'source' => ChatMessageDTO::SOURCE_SYSTEM,
        ]);

        $message = $actions->create((string) $tenant->id, $dto);

        $this->assertSame('pending', $message->status);
    }

    public function test_internal_note_emits_new_message_event_for_internal_clients(): void
    {
        Http::fake();
        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldIgnoreMissing();

        $activityBroadcast = $this->makeActivityBroadcast();

        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);
        $actions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $tenant = PlatformTenant::factory()->create();
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create();

        $dto = ChatMessageDTO::fromArray([
            'ticket_id' => (string) $ticket->id,
            'content' => 'Nota interna de transferência',
            'direction' => 'outgoing',
            'type' => 'internal_note',
            'source' => ChatMessageDTO::SOURCE_SYSTEM,
        ]);

        $message = $actions->create((string) $tenant->id, $dto);

        $this->assertSame('internal_note', $message->type);
        $this->assertSame('outgoing', $message->direction);
    }

    private function makeActivityBroadcast(): ChatActivityBroadcastService
    {
        Http::fake();

        return new ChatActivityBroadcastService(
            new ChatBroadcastService(new GatewayBroadcastService)
        );
    }

    private function makeTicketActions(ChatGatewayService $gateway, ChatActivityBroadcastService $activityBroadcast): ChatTicketActions
    {
        return new ChatTicketActions(
            $gateway,
            $activityBroadcast,
        );
    }

    private function makeMessageActions(ChatGatewayService $gateway, ChatTicketActions $ticketActions, ChatActivityBroadcastService $activityBroadcast): ChatMessageActions
    {
        return new ChatMessageActions(
            $gateway,
            $ticketActions,
            $activityBroadcast,
        );
    }
}
