<?php

declare(strict_types=1);

namespace Tests\Unit\Chat\Services;

use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatActivityBroadcastService;
use Domain\Chat\Services\ChatGatewayService;
use Domain\Chat\Services\ChatMessageGatewayDispatcher;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Testes para o token de instância Meta resolvido por
 * {@see ChatMessageGatewayDispatcher::resolveInstanceToken()}.
 *
 * O adapter Meta do gateway espera o formato `phoneNumberId:accessToken`
 * (não `webhook_token`) — sem isso, o envio falha com
 * "Invalid instance token format".
 */
class ChatMessageGatewayDispatcherMetaTokenTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_meta_instance_resolves_phone_number_id_and_access_token_as_gateway_token(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'meta',
            'settings_json' => [
                'phone_number_id' => '1234567890',
                'access_token' => 'EAAG-secret-token',
            ],
        ]);

        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $tenant->id,
            'instance_id' => $instance->id,
            'phone_e164' => '+5511999999999',
        ]);

        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'type' => 'text',
            'direction' => 'outgoing',
            'content' => 'Olá',
            'status' => 'pending',
        ]);

        $this->mock(ChatGatewayService::class, function ($mock): void {
            $mock->shouldReceive('sendText')
                ->once()
                ->withArgs(fn (string $token, array $payload): bool => $token === '1234567890:EAAG-secret-token')
                ->andReturn(['id' => 'wamid.OUT1']);
        });
        $this->mock(ChatActivityBroadcastService::class, function ($mock): void {
            $mock->shouldIgnoreMissing();
        });

        $dispatcher = app(ChatMessageGatewayDispatcher::class);
        $dispatcher->dispatch($message, $ticket);

        $message->refresh();
        $this->assertSame('sent', $message->status);
        $this->assertSame('wamid.OUT1', $message->external_id);
    }

    public function test_meta_instance_missing_access_token_fails_without_calling_gateway(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'provider' => 'meta',
            'settings_json' => [
                'phone_number_id' => '1234567890',
                // access_token ausente — token não deve ser resolvido.
            ],
        ]);

        $ticket = ChatTicket::factory()->create([
            'tenant_id' => $tenant->id,
            'instance_id' => $instance->id,
            'phone_e164' => '+5511999999999',
        ]);

        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'type' => 'text',
            'direction' => 'outgoing',
            'content' => 'Olá',
            'status' => 'pending',
        ]);

        $this->mock(ChatGatewayService::class, function ($mock): void {
            $mock->shouldNotReceive('sendText');
        });
        $this->mock(ChatActivityBroadcastService::class, function ($mock): void {
            $mock->shouldIgnoreMissing();
        });

        $dispatcher = app(ChatMessageGatewayDispatcher::class);
        $dispatcher->dispatch($message, $ticket);

        $message->refresh();
        $this->assertSame('failed', $message->status);
        $this->assertSame('Instance token or destination missing', $message->error_message);
    }
}
