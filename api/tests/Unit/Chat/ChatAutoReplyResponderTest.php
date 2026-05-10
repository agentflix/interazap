<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Chat\Actions\ChatTicketActions;
use Domain\Chat\Actions\ProcessChatMessageAction;
use Domain\Chat\Actions\SendChatMessageAction;
use Domain\Chat\Actions\VerifyContactWindowAction;
use Domain\Chat\Models\ChatAutoReplyCooldown;
use Domain\Chat\Models\ChatAutoReplyRule;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Jobs\ChatAutoReplyRespondJob;
use Domain\Chat\Services\ChatActivityBroadcastService;
use Domain\Chat\Services\ChatAutoReplyResponder;
use Domain\Chat\Services\ChatBroadcastService;
use Domain\Chat\Services\ChatGatewayService;
use Domain\Chat\Services\WebChatRedisPublisher;
use Domain\Platform\Models\PlatformTenant;
use Domain\Platform\Services\UazapiGatewayService;
use Domain\Shared\Infrastructure\Gateway\GatewayHttpClient;
use Domain\Shared\Services\GatewayBroadcastService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ChatAutoReplyResponderTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeTicketActions(ChatGatewayService $gateway, ChatActivityBroadcastService $activityBroadcast): ChatTicketActions
    {
        return new ChatTicketActions(
            $gateway,
            $activityBroadcast,
        );
    }

    private function makeMessageActions(ChatGatewayService $gateway, ChatTicketActions $ticketActions, ChatActivityBroadcastService $activityBroadcast): SendChatMessageAction
    {
        $processAction = new ProcessChatMessageAction($activityBroadcast);
        $webChatPublisher = Mockery::mock(WebChatRedisPublisher::class);
        $webChatPublisher->shouldIgnoreMissing();
        $verifyWindowAction = new VerifyContactWindowAction;

        return new SendChatMessageAction(
            $gateway,
            $ticketActions,
            $processAction,
            $webChatPublisher,
            $verifyWindowAction,
        );
    }

    public function test_respond_ignores_empty_body(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-1',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'instance_id' => $instance->id,
            'phone_e164' => '5511999999999',
        ]);

        $uazapi = Mockery::mock(UazapiGatewayService::class);
        $uazapi->shouldReceive('sendText')->never();

        Http::fake();
        $gateway = new ChatGatewayService(
            $uazapi,
            Mockery::mock(GatewayHttpClient::class),
        );
        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);
        $activityBroadcast = new ChatActivityBroadcastService($broadcast);
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);
        $messageActions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $service = new ChatAutoReplyResponder($messageActions);
        $service->respond($tenant->id, $ticket->id, '');

        $this->assertDatabaseCount('chat_messages', 0);
        $this->assertDatabaseCount('chat_auto_reply_cooldowns', 0);
    }

    public function test_dispatch_allows_duplicate_messages_for_same_ticket_and_body(): void
    {
        Queue::fake();

        $uazapi = Mockery::mock(UazapiGatewayService::class);
        $uazapi->shouldIgnoreMissing();

        Http::fake();
        $gateway = new ChatGatewayService(
            $uazapi,
            Mockery::mock(GatewayHttpClient::class),
        );
        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);
        $activityBroadcast = new ChatActivityBroadcastService($broadcast);
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);
        $messageActions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);
        $service = new ChatAutoReplyResponder($messageActions);

        $service->dispatch('tenant-1', 'ticket-1', 'menu', false);
        $service->dispatch('tenant-1', 'ticket-1', 'menu', false);

        Queue::assertPushed(ChatAutoReplyRespondJob::class, 2);
    }

    public function test_respond_creates_message_when_rule_matches_even_with_expired_cooldown_record(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-2',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'instance_id' => $instance->id,
            'phone_e164' => '5511999999999',
        ]);

        $rule = ChatAutoReplyRule::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Support',
            'trigger_text' => 'ajuda',
            'response_text' => 'Como posso ajudar?',
            'is_active' => true,
            'cooldown_seconds' => 60,
        ]);

        ChatAutoReplyCooldown::query()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'rule_id' => $rule->id,
            'cooldown_until' => now()->subMinute(),
        ]);

        Event::fake();

        $uazapi = Mockery::mock(UazapiGatewayService::class);
        $uazapi->shouldReceive('sendText')
            ->once()
            ->andReturn(['id' => 'msg-1']);

        Http::fake();
        $gateway = new ChatGatewayService(
            $uazapi,
            Mockery::mock(GatewayHttpClient::class),
        );
        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);
        $activityBroadcast = new ChatActivityBroadcastService($broadcast);
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);
        $messageActions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $service = new ChatAutoReplyResponder($messageActions);
        $service->respond($tenant->id, $ticket->id, 'Preciso de ajuda');

        $this->assertGreaterThan(0, ChatMessage::query()->count());
    }

    public function test_respond_ignores_active_cooldown_and_sends_rule_response(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-3',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'instance_id' => $instance->id,
            'phone_e164' => '5511999999999',
        ]);

        $rule = ChatAutoReplyRule::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Support',
            'trigger_text' => 'ajuda',
            'response_text' => 'Como posso ajudar?',
            'is_active' => true,
            'cooldown_seconds' => 60,
        ]);

        ChatAutoReplyCooldown::query()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'rule_id' => $rule->id,
            'cooldown_until' => now()->addMinutes(5),
        ]);

        $uazapi = Mockery::mock(UazapiGatewayService::class);
        $uazapi->shouldReceive('sendText')
            ->once()
            ->andReturn(['id' => 'msg-cooldown-ignored']);

        Http::fake();
        $gateway = new ChatGatewayService(
            $uazapi,
            Mockery::mock(GatewayHttpClient::class),
        );
        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);
        $activityBroadcast = new ChatActivityBroadcastService($broadcast);
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);
        $messageActions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $service = new ChatAutoReplyResponder($messageActions);
        $service->respond($tenant->id, $ticket->id, 'Preciso de ajuda');

        $this->assertDatabaseHas('chat_messages', [
            'ticket_id' => $ticket->id,
            'content' => 'Como posso ajudar?',
            'direction' => 'outgoing',
        ]);
    }

    public function test_respond_sends_welcome_message_on_first_interaction(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-welcome',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'instance_id' => $instance->id,
            'phone_e164' => '5511999999999',
        ]);

        ChatAutoReplyRule::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Menu de Boas-Vindas',
            'trigger_text' => 'menu',
            'response_text' => 'Olá! Bem-vindo ao nosso atendimento. Digite 1 para vendas, 2 para suporte.',
            'is_active' => true,
            'is_welcome' => true,
            'cooldown_seconds' => 300,
        ]);

        Event::fake();

        $uazapi = Mockery::mock(UazapiGatewayService::class);
        $uazapi->shouldReceive('sendText')
            ->once()
            ->andReturn(['id' => 'msg-welcome']);

        Http::fake();
        $gateway = new ChatGatewayService(
            $uazapi,
            Mockery::mock(GatewayHttpClient::class),
        );
        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);
        $activityBroadcast = new ChatActivityBroadcastService($broadcast);
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);
        $messageActions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $service = new ChatAutoReplyResponder($messageActions);
        $service->respond($tenant->id, $ticket->id, 'oi', isFirstInteraction: true);

        $this->assertDatabaseHas('chat_messages', [
            'ticket_id' => $ticket->id,
            'content' => 'Olá! Bem-vindo ao nosso atendimento. Digite 1 para vendas, 2 para suporte.',
            'direction' => 'outgoing',
        ]);

        $this->assertDatabaseCount('chat_auto_reply_cooldowns', 0);
    }

    public function test_respond_does_not_send_welcome_on_subsequent_interactions_without_keyword_match(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-no-welcome',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'instance_id' => $instance->id,
            'phone_e164' => '5511999999999',
        ]);

        ChatAutoReplyRule::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Menu de Boas-Vindas',
            'trigger_text' => 'menu',
            'response_text' => 'Bem-vindo!',
            'is_active' => true,
            'is_welcome' => true,
            'cooldown_seconds' => 0,
        ]);

        $uazapi = Mockery::mock(UazapiGatewayService::class);
        $uazapi->shouldReceive('sendText')->never();

        Http::fake();
        $gateway = new ChatGatewayService(
            $uazapi,
            Mockery::mock(GatewayHttpClient::class),
        );
        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);
        $activityBroadcast = new ChatActivityBroadcastService($broadcast);
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);
        $messageActions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $service = new ChatAutoReplyResponder($messageActions);
        $service->respond($tenant->id, $ticket->id, 'oi', isFirstInteraction: false);

        $this->assertDatabaseCount('chat_messages', 0);
    }

    public function test_respond_retriggers_welcome_on_subsequent_interactions_when_keyword_matches(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-retrigger-welcome',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'instance_id' => $instance->id,
            'phone_e164' => '5511999999999',
        ]);

        ChatAutoReplyRule::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Menu de Boas-Vindas',
            'trigger_text' => 'menu',
            'response_text' => 'Oi! Este é o menu de opções.',
            'is_active' => true,
            'is_welcome' => true,
            'cooldown_seconds' => 0,
        ]);

        $uazapi = Mockery::mock(UazapiGatewayService::class);
        $uazapi->shouldReceive('sendText')
            ->once()
            ->andReturn(['id' => 'msg-retrigger']);

        Http::fake();
        $gateway = new ChatGatewayService(
            $uazapi,
            Mockery::mock(GatewayHttpClient::class),
        );
        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);
        $activityBroadcast = new ChatActivityBroadcastService($broadcast);
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);
        $messageActions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $service = new ChatAutoReplyResponder($messageActions);
        $service->respond($tenant->id, $ticket->id, 'menu', isFirstInteraction: false);

        $this->assertDatabaseHas('chat_messages', [
            'ticket_id' => $ticket->id,
            'content' => 'Oi! Este é o menu de opções.',
            'direction' => 'outgoing',
            'source' => 'bot',
        ]);
    }

    public function test_respond_retriggers_welcome_even_when_rule_has_active_cooldown(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-retrigger-cooldown',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'instance_id' => $instance->id,
            'phone_e164' => '5511999999999',
        ]);

        $welcomeRule = ChatAutoReplyRule::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Menu de Boas-Vindas',
            'trigger_text' => 'menu',
            'response_text' => 'Oi! Este é o menu de opções.',
            'is_active' => true,
            'is_welcome' => true,
            'cooldown_seconds' => 300,
        ]);

        ChatAutoReplyCooldown::query()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'rule_id' => $welcomeRule->id,
            'cooldown_until' => now()->addMinutes(5),
        ]);

        $uazapi = Mockery::mock(UazapiGatewayService::class);
        $uazapi->shouldReceive('sendText')
            ->once()
            ->andReturn(['id' => 'msg-retrigger-cooldown-ignored']);

        Http::fake();
        $gateway = new ChatGatewayService(
            $uazapi,
            Mockery::mock(GatewayHttpClient::class),
        );
        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);
        $activityBroadcast = new ChatActivityBroadcastService($broadcast);
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);
        $messageActions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $service = new ChatAutoReplyResponder($messageActions);
        $service->respond($tenant->id, $ticket->id, 'menu', isFirstInteraction: false);

        $this->assertDatabaseHas('chat_messages', [
            'ticket_id' => $ticket->id,
            'content' => 'Oi! Este é o menu de opções.',
            'direction' => 'outgoing',
            'source' => 'bot',
        ]);
    }

    public function test_respond_ignores_keyword_processing_when_ticket_is_under_human_takeover(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'webhook_token' => 'tok-human-takeover',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenant->id)->create([
            'instance_id' => $instance->id,
            'phone_e164' => '5511999999999',
            'human_takeover_at' => now(),
        ]);

        ChatAutoReplyRule::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Menu de Boas-Vindas',
            'trigger_text' => 'menu',
            'response_text' => 'Oi! Este é o menu de opções.',
            'is_active' => true,
            'is_welcome' => true,
            'cooldown_seconds' => 0,
        ]);

        ChatAutoReplyRule::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Regra 1',
            'trigger_text' => '1',
            'response_text' => 'Você escolheu a opção 1',
            'is_active' => true,
            'is_welcome' => false,
            'cooldown_seconds' => 0,
        ]);

        $uazapi = Mockery::mock(UazapiGatewayService::class);
        $uazapi->shouldReceive('sendText')->never();

        Http::fake();
        $gateway = new ChatGatewayService(
            $uazapi,
            Mockery::mock(GatewayHttpClient::class),
        );
        $broadcast = new ChatBroadcastService(new GatewayBroadcastService);
        $activityBroadcast = new ChatActivityBroadcastService($broadcast);
        $ticketActions = $this->makeTicketActions($gateway, $activityBroadcast);
        $messageActions = $this->makeMessageActions($gateway, $ticketActions, $activityBroadcast);

        $service = new ChatAutoReplyResponder($messageActions);
        $service->respond($tenant->id, $ticket->id, 'menu 1', isFirstInteraction: false);

        $this->assertDatabaseCount('chat_messages', 0);
    }
}
