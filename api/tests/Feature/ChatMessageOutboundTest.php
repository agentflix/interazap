<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\DTOs\ChatMessageDTO;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatSession;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ChatMessageOutboundTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Configurar gateway antes de o client ser instanciado
        config()->set('services.gateway.url', 'http://gateway.test');
    }

    public function test_send_outgoing_message_calls_gateway(): void
    {
        $user = AuthUser::factory()->create();
        $perm = \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => 'chat.messages.create', 'guard_name' => 'sanctum'], ['id' => Str::orderedUuid()]);
        $user->givePermissionTo($perm);
        $this->be($user, 'sanctum');

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'webhook_token' => 'tok-123',
        ]);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'instance_id' => $instance->id,
            'phone' => '5511999999999',
        ]);

        config()->set('services.gateway.url', 'http://gateway.test');
        Http::fake([
            'http://gateway.test/*' => Http::response(['messageid' => 'msg-123'], 200),
        ]);

        $res = $this->postJson("/api/chat/tickets/{$ticket->id}/messages", [
            'content' => 'Olá',
            'direction' => 'outgoing',
        ])->assertCreated();

        $this->assertNotNull($res->json('data.id'));
        $messageId = $res->json('data.id');

        $message = ChatMessage::query()->find($messageId);
        $this->assertNotNull($message);
        $this->assertSame('sent', $message->status);
        $this->assertSame('msg-123', $message->external_id);

        Http::assertSent(fn ($request): bool => $request->url() === 'http://gateway.test/send/text'
            && $request['number'] === '5511999999999'
            && $request['text'] === 'Olá');
    }

    public function test_send_file_message_calls_gateway(): void
    {
        $user = AuthUser::factory()->create();
        $perm = \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => 'chat.messages.create', 'guard_name' => 'sanctum'], ['id' => Str::orderedUuid()]);
        $user->givePermissionTo($perm);
        $this->be($user, 'sanctum');

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'webhook_token' => 'tok-123',
        ]);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'instance_id' => $instance->id,
            'phone' => '5511999999999',
        ]);

        Http::fake([
            'http://gateway.test/*' => Http::response(['messageid' => 'file-123'], 200),
        ]);

        $res = $this->postJson("/api/chat/tickets/{$ticket->id}/messages", [
            'content' => 'Confira o arquivo',
            'direction' => 'outgoing',
            'type' => 'file',
            'file_url' => 'https://example.com/file.pdf',
        ])->assertCreated();

        $messageId = $res->json('data.id');
        $message = ChatMessage::query()->find($messageId);
        $this->assertNotNull($message);
        $this->assertSame('sent', $message->status);
        $this->assertSame('file-123', $message->external_id);

        Http::assertSent(fn ($request): bool => $request->url() === 'http://gateway.test/send/file'
            && $request['number'] === '5511999999999'
            && $request['file'] === 'https://example.com/file.pdf');
    }

    public function test_send_message_without_direction_defaults_to_outgoing(): void
    {
        $user = AuthUser::factory()->create();
        $perm = \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => 'chat.messages.create', 'guard_name' => 'sanctum'], ['id' => Str::orderedUuid()]);
        $user->givePermissionTo($perm);
        $this->be($user, 'sanctum');

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'webhook_token' => 'tok-999',
        ]);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'instance_id' => $instance->id,
            'phone' => '5511988887777',
        ]);

        Http::fake([
            'http://gateway.test/*' => Http::response(['messageid' => 'msg-999'], 200),
        ]);

        $response = $this->postJson("/api/chat/tickets/{$ticket->id}/messages", [
            'content' => 'Mensagem sem direction',
        ])->assertCreated();

        $message = ChatMessage::query()->find($response->json('data.id'));
        $this->assertNotNull($message);
        $this->assertSame('outgoing', $message->direction);
        $this->assertFalse((bool) $message->is_from_contact);
        $this->assertSame($user->id, $message->user_id);
        $this->assertSame('sent', $message->status);

        Http::assertSent(fn ($request): bool => $request->url() === 'http://gateway.test/send/text'
            && $request['number'] === '5511988887777'
            && $request['text'] === 'Mensagem sem direction');
    }

    public function test_requires_permission_to_send_message(): void
    {
        $user = AuthUser::factory()->create();
        $this->be($user, 'sanctum'); // sem permissão

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create();

        $this->postJson("/api/chat/tickets/{$ticket->id}/messages", [
            'content' => 'Olá',
            'direction' => 'outgoing',
        ])->assertStatus(403);
    }

    public function test_validation_errors_on_message_payload(): void
    {
        $user = AuthUser::factory()->create();
        $perm = \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => 'chat.messages.create', 'guard_name' => 'sanctum'], ['id' => Str::orderedUuid()]);
        $user->givePermissionTo($perm);
        $this->be($user, 'sanctum');

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create();

        $this->postJson("/api/chat/tickets/{$ticket->id}/messages", [
            // missing content
            'direction' => 'outgoing',
        ])->assertStatus(422)->assertJsonValidationErrors(['content']);
    }

    public function test_tenant_cannot_send_message_on_other_tenant_ticket(): void
    {
        $userA = AuthUser::factory()->create();
        $userB = AuthUser::factory()->create();
        $perm = \Domain\Auth\Models\AuthPermission::query()->firstOrCreate(['name' => 'chat.messages.create', 'guard_name' => 'sanctum'], ['id' => Str::orderedUuid()]);
        $userA->givePermissionTo($perm);
        $this->be($userA, 'sanctum');

        $ticketB = ChatTicket::factory()->forTenant($userB->tenant_id)->create();

        $this->postJson("/api/chat/tickets/{$ticketB->id}/messages", [
            'content' => 'forbidden',
            'direction' => 'outgoing',
        ])->assertStatus(404);
    }

    /**
     * Mensagem outgoing recebida via webhook (fromMe: true de outro dispositivo)
     * NÃO deve ser reenviada para o gateway — evita duplicação no WhatsApp.
     */
    public function test_outgoing_webhook_message_does_not_call_gateway(): void
    {
        $user = AuthUser::factory()->create();

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'webhook_token' => 'tok-webhook',
        ]);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'instance_id' => $instance->id,
            'phone' => '5511999999999',
        ]);

        // Iniciar interceptação APÓS criação dos modelos (evitar ruído do AutopilotTriggerFired)
        Http::fake();

        $actions = app(\Domain\Chat\Actions\ChatMessageActions::class);

        $dto = new ChatMessageDTO(
            ticketId: (string) $ticket->id,
            content: 'Mensagem do outro celular',
            direction: 'outgoing',
            type: 'text',
            isFromContact: false,
            source: ChatMessageDTO::SOURCE_WEBHOOK,
        );

        $message = $actions->create((string) $user->tenant_id, $dto);

        $this->assertNotNull($message->id);
        $this->assertSame('outgoing', $message->direction);

        // Gateway NÃO deve receber chamada — mensagem veio de webhook, não de agente
        Http::assertNothingSent();
    }

    /**
     * Mensagem outgoing originada pelo bot (source=bot) DEVE ser entregue via gateway.
     * Garante que o guard SOURCE_WEBHOOK não bloqueia bot/agent/system.
     */
    public function test_outgoing_bot_message_calls_gateway(): void
    {
        $user = AuthUser::factory()->create();

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'webhook_token' => 'tok-bot',
        ]);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'instance_id' => $instance->id,
            'phone' => '5511999999999',
        ]);

        Http::fake([
            'http://gateway.test/*' => Http::response(['messageid' => 'bot-msg-1'], 200),
        ]);

        $actions = app(\Domain\Chat\Actions\ChatMessageActions::class);

        $dto = new ChatMessageDTO(
            ticketId: (string) $ticket->id,
            content: 'Resposta automática do bot',
            direction: 'outgoing',
            type: 'text',
            isFromContact: false,
            source: ChatMessageDTO::SOURCE_BOT,
        );

        $message = $actions->create((string) $user->tenant_id, $dto);

        $this->assertNotNull($message->id);
        $this->assertSame('outgoing', $message->direction);

        // Bot deve chamar o gateway para entregar a mensagem
        Http::assertSent(fn ($request): bool => str_contains($request->url(), 'gateway.test'));
    }

    public function test_outgoing_ai_message_emits_chat_activity_for_attendant_and_chat_list(): void
    {
        $user = AuthUser::factory()->create();

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'webhook_token' => 'tok-ai',
        ]);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'instance_id' => $instance->id,
            'phone' => '5511999999999',
        ]);

        $publishedPayloads = [];
        $redisConnection = Mockery::mock();
        $redisConnection->shouldReceive('publish')
            ->atLeast()->once()
            ->with('ws.events', Mockery::on(function (string $raw) use (&$publishedPayloads): bool {
                $publishedPayloads[] = json_decode($raw, true);

                return true;
            }))
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->atLeast()->once()
            ->andReturn($redisConnection);

        Http::fake([
            'http://gateway.test/*' => Http::response(['messageid' => 'ai-msg-1'], 200),
        ]);

        $actions = app(\Domain\Chat\Actions\ChatMessageActions::class);

        $dto = new ChatMessageDTO(
            ticketId: (string) $ticket->id,
            content: 'Resposta da IA',
            direction: 'outgoing',
            type: 'text',
            isFromContact: false,
            source: ChatMessageDTO::SOURCE_AI,
        );

        $message = $actions->create((string) $user->tenant_id, $dto);

        $this->assertNotNull($message->id);
        $this->assertSame('outgoing', $message->direction);

        $activityPayloads = array_values(array_filter(
            $publishedPayloads,
            static fn (array $payload): bool => ($payload['event'] ?? null) === 'chat.activity',
        ));

        $this->assertNotEmpty($activityPayloads, 'Deve publicar chat.activity para atualizar painel do atendente');

        $hasReceivedWithAiSource = false;
        $hasChatListUpdate = false;

        foreach ($activityPayloads as $payload) {
            $subevents = $payload['data']['subevents'] ?? [];
            foreach ($subevents as $subevent) {
                if (($subevent['type'] ?? null) === 'msg.received'
                    && ($subevent['data']['message']['source'] ?? null) === ChatMessageDTO::SOURCE_AI
                ) {
                    $hasReceivedWithAiSource = true;
                }

                if (($subevent['type'] ?? null) === 'chat.list.updated') {
                    $hasChatListUpdate = true;
                }
            }
        }

        $this->assertTrue($hasReceivedWithAiSource, 'Payload deve conter msg.received com source=ai');
        $this->assertTrue($hasChatListUpdate, 'Payload deve conter chat.list.updated para atualizar lista de tickets');
    }

    public function test_outgoing_bot_message_emits_chat_activity_for_attendant_and_chat_list(): void
    {
        $user = AuthUser::factory()->create();

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'webhook_token' => 'tok-bot-webchat',
        ]);

        $ticket = ChatTicket::factory()->forTenant($user->tenant_id)->create([
            'instance_id' => $instance->id,
            'phone' => '5511999999999',
        ]);

        ChatSession::factory()->forTicket($ticket)->create([
            'tenant_id' => $user->tenant_id,
            'contact_id' => $ticket->contact_id,
        ]);

        $publishedPayloads = [];
        $redisConnection = Mockery::mock();
        $redisConnection->shouldReceive('publish')
            ->atLeast()->once()
            ->with('ws.events', Mockery::on(function (string $raw) use (&$publishedPayloads): bool {
                $publishedPayloads[] = json_decode($raw, true);

                return true;
            }))
            ->andReturn(1);

        Redis::shouldReceive('connection')
            ->atLeast()->once()
            ->andReturn($redisConnection);

        Http::fake();

        $actions = app(\Domain\Chat\Actions\ChatMessageActions::class);

        $dto = new ChatMessageDTO(
            ticketId: (string) $ticket->id,
            content: 'Resposta automática do bot (webchat)',
            direction: 'outgoing',
            type: 'text',
            isFromContact: false,
            source: ChatMessageDTO::SOURCE_BOT,
        );

        $message = $actions->create((string) $user->tenant_id, $dto);

        $this->assertNotNull($message->id);

        $activityPayloads = array_values(array_filter(
            $publishedPayloads,
            static fn (array $payload): bool => ($payload['event'] ?? null) === 'chat.activity',
        ));

        $this->assertNotEmpty($activityPayloads, 'Deve publicar chat.activity para atualizar painel do atendente');

        $hasReceivedWithBotSource = false;
        $hasChatListUpdate = false;

        foreach ($activityPayloads as $payload) {
            $subevents = $payload['data']['subevents'] ?? [];
            foreach ($subevents as $subevent) {
                if (($subevent['type'] ?? null) === 'msg.received'
                    && ($subevent['data']['message']['source'] ?? null) === ChatMessageDTO::SOURCE_BOT
                ) {
                    $hasReceivedWithBotSource = true;
                }

                if (($subevent['type'] ?? null) === 'chat.list.updated') {
                    $hasChatListUpdate = true;
                }
            }
        }

        $this->assertTrue($hasReceivedWithBotSource, 'Payload deve conter msg.received com source=bot');
        $this->assertTrue($hasChatListUpdate, 'Payload deve conter chat.list.updated para atualizar lista de tickets');
    }
}
