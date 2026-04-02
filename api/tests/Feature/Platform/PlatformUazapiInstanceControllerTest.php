<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformUazapiInstance;
use Domain\Platform\Services\UazapiGatewayService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class PlatformUazapiInstanceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function grantWhatsappManagePermission(AuthUser $user): void
    {
        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'whatsapp.manage', 'guard_name' => 'sanctum'],
            ['id' => Str::orderedUuid()],
        );

        $user->givePermissionTo($permission);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_can_list_and_show_instances(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantWhatsappManagePermission($user);
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Instance',
            'system_name' => 'uazapi',
            'token' => 'tok-list',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/platform/uazapi/instances')
            ->assertOk()
            ->assertJsonFragment(['id' => $instance->id]);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/platform/uazapi/instances/{$instance->id}")
            ->assertOk()
            ->assertJsonFragment(['id' => $instance->id]);
    }

    public function test_can_create_instance_via_gateway(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantWhatsappManagePermission($user);

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('initInstance')
            ->once()
            ->andReturn([
                'name' => 'Instance',
                'token' => 'tok-1',
                'status' => 'connected',
            ]);
        $this->app->instance(UazapiGatewayService::class, $gateway);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/platform/uazapi/instances', [
                'name' => 'Instance',
                'system_name' => 'uazapi',
            ])
            ->assertCreated()
            ->assertJsonFragment(['token' => 'tok-1']);
    }

    public function test_can_request_status_connect_disconnect_and_delete(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantWhatsappManagePermission($user);
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Instance',
            'system_name' => 'uazapi',
            'token' => 'tok-2',
        ]);

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('status')->once()->with('tok-2')->andReturn(['status' => 'connected']);
        $gateway->shouldReceive('connectInstance')->once()->with('tok-2', [])->andReturn(['status' => 'connecting']);
        $gateway->shouldReceive('disconnectInstance')->once()->with('tok-2')->andReturn(['status' => 'disconnected']);
        $gateway->shouldReceive('deleteInstance')->once()->with('tok-2')->andReturn(['ok' => true]);
        $this->app->instance(UazapiGatewayService::class, $gateway);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/platform/uazapi/instances/{$instance->id}/status")
            ->assertOk()
            ->assertJsonFragment(['status' => 'connected']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/platform/uazapi/instances/{$instance->id}/connect", [])
            ->assertOk()
            ->assertJsonFragment(['status' => 'connecting']);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/platform/uazapi/instances/{$instance->id}/disconnect")
            ->assertOk()
            ->assertJsonFragment(['status' => 'disconnected']);

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/platform/uazapi/instances/{$instance->id}")
            ->assertNoContent();
    }

    public function test_can_update_admin_fields(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantWhatsappManagePermission($user);
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Instance',
            'system_name' => 'uazapi',
            'token' => 'tok-admin',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/platform/uazapi/instances/{$instance->id}/admin-fields", [
                'config' => [
                    'custom_key_01' => 'custom-value-01',
                    'custom_key_02' => 'custom-value-02',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.config.custom_key_01', 'custom-value-01')
            ->assertJsonPath('data.config.custom_key_02', 'custom-value-02');
    }

    public function test_can_update_instance_name(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantWhatsappManagePermission($user);
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Old Name',
            'system_name' => 'uazapi',
            'token' => 'tok-name',
        ]);

        $this->actingAs($user, 'sanctum')
            ->patchJson("/api/platform/uazapi/instances/{$instance->id}/name", [
                'name' => 'New Instance Name',
            ])
            ->assertOk()
            ->assertJsonFragment(['name' => 'New Instance Name']);
    }

    public function test_can_update_profile_image_when_connected(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantWhatsappManagePermission($user);
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Instance',
            'system_name' => 'uazapi',
            'token' => 'tok-profile',
            'status' => 'connected',
        ]);

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('updateProfileImage')
            ->once()
            ->with('tok-profile', 'https://example.com/avatar.jpg')
            ->andReturn([
                'success' => true,
                'profile' => ['profilePicUrl' => 'https://example.com/avatar.jpg'],
            ]);
        $this->app->instance(UazapiGatewayService::class, $gateway);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/platform/uazapi/instances/{$instance->id}/profile-image", [
                'image' => 'https://example.com/avatar.jpg',
            ])
            ->assertOk()
            ->assertJson(['success' => true]);
    }

    public function test_profile_image_returns_422_when_not_connected(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantWhatsappManagePermission($user);
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Instance',
            'system_name' => 'uazapi',
            'token' => 'tok-profile-offline',
            'status' => 'disconnected',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/platform/uazapi/instances/{$instance->id}/profile-image", [
                'image' => 'https://example.com/avatar.jpg',
            ])
            ->assertStatus(422);
    }

    public function test_can_update_presence_when_connected(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantWhatsappManagePermission($user);
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Instance',
            'system_name' => 'uazapi',
            'token' => 'tok-presence',
            'status' => 'connected',
        ]);

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('updatePresence')
            ->once()
            ->with('tok-presence', 'available')
            ->andReturn(['response' => 'Presence updated successfully']);
        $this->app->instance(UazapiGatewayService::class, $gateway);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/platform/uazapi/instances/{$instance->id}/presence", [
                'presence' => 'available',
            ])
            ->assertOk()
            ->assertJsonPath('data.response.response', 'Presence updated successfully');
    }

    public function test_presence_returns_422_when_not_connected(): void
    {
        /** @var AuthUser $user */
        $user = AuthUser::factory()->create();
        $this->grantWhatsappManagePermission($user);
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $user->tenant_id,
            'name' => 'Instance',
            'system_name' => 'uazapi',
            'token' => 'tok-presence-offline',
            'status' => 'disconnected',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson("/api/platform/uazapi/instances/{$instance->id}/presence", [
                'presence' => 'available',
            ])
            ->assertStatus(422);
    }
}
