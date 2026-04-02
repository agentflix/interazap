<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatGatewayService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ChatPresenceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_presence_endpoint(): void
    {
        $user = AuthUser::factory()->create();
        $tenantId = (string) $user->tenant_id;

        $permView = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.tickets.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $user->givePermissionTo($permView);

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenantId,
            'webhook_token' => 'tok-pres',
        ]);
        $ticket = ChatTicket::factory()->forTenant($tenantId)->create([
            'instance_id' => $instance->id,
            'phone' => '5511999999999',
        ]);

        $gateway = Mockery::mock(ChatGatewayService::class);
        $gateway->shouldReceive('sendPresence')
            ->once()
            ->andReturn(['ok' => true]);
        $this->app->instance(ChatGatewayService::class, $gateway);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/chat/tickets/{$ticket->id}/presence", [
                'presence' => 'composing',
                'delay' => 1000,
            ])
            ->assertOk()
            ->assertJsonFragment(['ok' => true]);
    }
}
