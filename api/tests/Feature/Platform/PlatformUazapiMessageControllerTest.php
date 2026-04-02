<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformUazapiInstance;
use Domain\Platform\Services\UazapiGatewayService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class PlatformUazapiMessageControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_send_text_and_file_proxy_to_gateway(): void
    {
        $user = AuthUser::factory()->create();
        $this->grantWhatsappManage($user);

        PlatformUazapiInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'token' => 'tok-x',
        ]);

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('sendText')->once()->with('tok-x', Mockery::type('array'))->andReturn(['messageid' => 'm1']);
        $gateway->shouldReceive('sendFile')->once()->with('tok-x', Mockery::type('array'))->andReturn(['messageid' => 'm2']);
        $this->app->instance(UazapiGatewayService::class, $gateway);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/platform/uazapi/instances/tok-x/messages/text', [
                'number' => '5511999999',
                'text' => 'Olá',
            ])
            ->assertOk()
            ->assertJsonFragment(['messageid' => 'm1']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/platform/uazapi/instances/tok-x/messages/file', [
                'number' => '5511999999',
                'url' => 'https://files.test/doc.pdf',
                'caption' => 'Doc',
            ])
            ->assertOk()
            ->assertJsonFragment(['messageid' => 'm2']);
    }

    private function grantWhatsappManage(AuthUser $user): void
    {
        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'whatsapp.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $role = AuthRole::query()->firstOrCreate(
            ['name' => 'platform-whatsapp-manager', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $role->givePermissionTo($permission);
        $user->assignRole($role);
    }
}
