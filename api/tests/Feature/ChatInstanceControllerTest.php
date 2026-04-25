<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Services\ChatChannelConnector;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class ChatInstanceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_chat_instance_endpoints(): void
    {
        $user = AuthUser::factory()->create();
        $viewPermission = AuthPermission::query()->firstOrCreate(
            ['name' => 'channels.whatsapp.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $managePermission = AuthPermission::query()->firstOrCreate(
            ['name' => 'channels.whatsapp.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $user->givePermissionTo([$viewPermission, $managePermission]);

        $connector = Mockery::mock(ChatChannelConnector::class);
        $connector->shouldReceive('configureWebhook')->andReturn(['ok' => true]);
        $connector->shouldReceive('connect')->andReturn([
            'mode' => 'qr',
            'qr_code' => 'data:image/png;base64,abc',
            'pair_code' => null,
            'expires_at' => now()->addMinutes(5)->toIso8601String(),
            'provider' => 'uazapi',
        ]);
        $this->app->instance(ChatChannelConnector::class, $connector);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/channels')
            ->assertOk();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/channels', [
                'name' => 'Instancia',
                'provider' => 'uazapi',
                'token' => 'tok-123',
                'settings' => [
                    'cellphone' => '5511999999999',
                    'send_attendant_name' => true,
                    'channel_fallback_message' => '  Mensagem de fallback da integracao  ',
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.settings.send_attendant_name', true)
            ->assertJsonPath('data.settings.channel_fallback_message', 'Mensagem de fallback da integracao')
            ->json('data');

        $instanceId = $response['id'];

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/channels/{$instanceId}")
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/channels/{$instanceId}", [
                'name' => 'Instancia 2',
                'provider' => 'uazapi',
                'token' => 'tok-123',
                'settings' => [
                    'channel_fallback_message' => 'Fallback atualizado',
                ],
            ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'Instancia 2'])
            ->assertJsonPath('data.settings.channel_fallback_message', 'Fallback atualizado');

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/channels/{$instanceId}/toggle-active")
            ->assertNoContent();

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/channels/{$instanceId}/connect", [
                'mode' => 'qr',
            ])
            ->assertOk()
            ->assertJsonFragment(['mode' => 'qr']);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/channels/{$instanceId}/status")
            ->assertOk()
            ->assertJsonFragment(['mode' => 'qr']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/channels/{$instanceId}/disconnect")
            ->assertOk();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/channels/{$instanceId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('chat_instances', ['id' => $instanceId]);
    }

    public function test_store_rejects_token_longer_than_255_characters(): void
    {
        $user = AuthUser::factory()->create();
        $managePermission = AuthPermission::query()->firstOrCreate(
            ['name' => 'channels.whatsapp.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $user->givePermissionTo($managePermission);

        $connector = Mockery::mock(ChatChannelConnector::class);
        $connector->shouldReceive('configureWebhook')->never();
        $this->app->instance(ChatChannelConnector::class, $connector);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/channels', [
                'name' => 'Instancia inválida',
                'provider' => 'uazapi',
                'token' => str_repeat('a', 256),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['token']);
    }

    public function test_store_rejects_channel_fallback_message_longer_than_2000_characters(): void
    {
        $user = AuthUser::factory()->create();
        $managePermission = AuthPermission::query()->firstOrCreate(
            ['name' => 'channels.whatsapp.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $user->givePermissionTo($managePermission);

        $connector = Mockery::mock(ChatChannelConnector::class);
        $this->app->instance(ChatChannelConnector::class, $connector);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/channels', [
                'name' => 'Instancia inválida',
                'provider' => 'uazapi',
                'token' => 'tok-123',
                'settings' => [
                    'channel_fallback_message' => str_repeat('a', 2001),
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['settings.channel_fallback_message']);
    }

    public function test_legacy_instance_without_channel_fallback_message_keeps_settings_shape(): void
    {
        $user = AuthUser::factory()->create();
        $managePermission = AuthPermission::query()->firstOrCreate(
            ['name' => 'channels.whatsapp.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $viewPermission = AuthPermission::query()->firstOrCreate(
            ['name' => 'channels.whatsapp.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $user->givePermissionTo([$viewPermission, $managePermission]);

        $connector = Mockery::mock(ChatChannelConnector::class);
        $connector->shouldReceive('configureWebhook')->andReturn(['ok' => true]);
        $this->app->instance(ChatChannelConnector::class, $connector);

        $created = $this->actingAs($user, 'sanctum')
            ->postJson('/api/channels', [
                'name' => 'Instancia Legada',
                'provider' => 'uazapi',
                'token' => 'tok-legacy',
                'settings' => [
                    'send_attendant_name' => true,
                ],
            ])
            ->assertCreated()
            ->json('data');

        $this->assertArrayNotHasKey('channel_fallback_message', $created['settings']);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/channels/'.$created['id'])
            ->assertOk()
            ->assertJsonMissingPath('data.settings.channel_fallback_message');
    }

    public function test_cannot_delete_connected_channel(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $managePermission = AuthPermission::query()->firstOrCreate(
            ['name' => 'channels.whatsapp.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $user->givePermissionTo($managePermission);

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'connected',
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/channels/{$instance->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Não é possível excluir um canal conectado. Desconecte primeiro.');

        $this->assertDatabaseHas('chat_instances', ['id' => $instance->id]);
    }

    public function test_cannot_delete_connected_channel_cross_tenant(): void
    {
        $tenantA = PlatformTenant::factory()->create();
        $tenantB = PlatformTenant::factory()->create();

        $userA = AuthUser::factory()->create([
            'tenant_id' => $tenantA->id,
        ]);
        $managePermission = AuthPermission::query()->firstOrCreate(
            ['name' => 'channels.whatsapp.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $userA->givePermissionTo($managePermission);

        $instanceB = ChatInstance::factory()->create([
            'tenant_id' => $tenantB->id,
            'status' => 'connected',
        ]);

        $this->actingAs($userA, 'sanctum')
            ->deleteJson("/api/channels/{$instanceB->id}")
            ->assertNotFound();

        $this->assertDatabaseHas('chat_instances', ['id' => $instanceB->id]);
    }

    public function test_can_delete_disconnected_channel(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $managePermission = AuthPermission::query()->firstOrCreate(
            ['name' => 'channels.whatsapp.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $user->givePermissionTo($managePermission);

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'disconnected',
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/channels/{$instance->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('chat_instances', ['id' => $instance->id]);
    }

    public function test_can_delete_channel_with_qr_status(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $managePermission = AuthPermission::query()->firstOrCreate(
            ['name' => 'channels.whatsapp.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );
        $user->givePermissionTo($managePermission);

        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenant->id,
            'status' => 'qr',
        ]);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/channels/{$instance->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('chat_instances', ['id' => $instance->id]);
    }
}
