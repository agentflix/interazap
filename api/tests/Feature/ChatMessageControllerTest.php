<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatGatewayService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ChatMessageControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_list_and_send_contact_and_location_messages(): void
    {
        $user = AuthUser::factory()->create();
        $tenantId = (string) $user->tenant_id;

        $permView = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.messages.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $permCreate = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.messages.create', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $user->givePermissionTo($permView, $permCreate);

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenantId,
            'webhook_token' => 'tok-msg',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenantId)->create([
            'instance_id' => $instance->id,
            'phone' => '5511999999999',
        ]);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldReceive('sendContact')->once()->andReturn(['messageid' => 'contact-1']);
        $gateway->shouldReceive('sendLocation')->once()->andReturn(['messageid' => 'loc-1']);
        $this->app->instance(ChatGatewayService::class, $gateway);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/chat/tickets/{$ticket->id}/messages")
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticket->id}/messages/contact", [
                'contact' => [
                    'fullName' => 'Contato',
                    'phoneNumber' => '5511',
                    'organization' => 'InteraZap',
                ],
            ])
            ->assertCreated()
            ->assertJsonFragment(['type' => 'contact']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticket->id}/messages/location", [
                'location' => [
                    'latitude' => -23.0,
                    'longitude' => -46.0,
                    'name' => 'HQ',
                ],
            ])
            ->assertCreated()
            ->assertJsonFragment(['type' => 'location']);
    }

    public function test_react_edit_and_delete_message(): void
    {
        $user = AuthUser::factory()->create();
        $tenantId = (string) $user->tenant_id;

        Gate::shouldReceive('authorize')->andReturnTrue();

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenantId,
            'webhook_token' => 'tok-react',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenantId)->create([
            'instance_id' => $instance->id,
            'remote_jid' => '5511999999999@s.whatsapp.net',
        ]);
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'direction' => 'outgoing',
            'external_id' => 'ext-react',
        ]);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldReceive('reactToMessage')->once()->andReturn(['ok' => true]);
        $gateway->shouldReceive('editMessage')->once()->andReturn(['ok' => true]);
        $gateway->shouldReceive('deleteMessage')->once()->andReturn(['ok' => true]);
        $this->app->instance(ChatGatewayService::class, $gateway);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticket->id}/messages/{$message->id}/react", [
                'emoji' => '👍',
            ])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticket->id}/messages/{$message->id}/edit", [
                'content' => 'Editada',
            ])
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/chat/tickets/{$ticket->id}/messages/{$message->id}")
            ->assertOk();
    }
}
