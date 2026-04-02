<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Http\Controllers\PlatformUazapiMessageController;
use Domain\Platform\Http\Requests\PlatformUazapiSendFileRequest;
use Domain\Platform\Http\Requests\PlatformUazapiSendTextRequest;
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

    public function test_send_text_delegates_to_gateway(): void
    {
        $user = AuthUser::factory()->create();
        $this->grantWhatsappManage($user);

        PlatformUazapiInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'token' => 'tok-600',
        ]);

        $this->actingAs($user, 'sanctum');

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('sendText')
            ->once()
            ->with('tok-600', ['number' => '1', 'text' => 'Oi'])
            ->andReturn(['messageid' => 'm1']);

        $controller = new PlatformUazapiMessageController($gateway);
        $request = Mockery::mock(PlatformUazapiSendTextRequest::class);
        $request->shouldReceive('validated')->once()->andReturn(['number' => '1', 'text' => 'Oi']);

        $response = $controller->sendText($request, 'tok-600');
        $payload = $response->getData(true);

        $this->assertSame('Mensagem enviada', $payload['message']);
        $this->assertSame('m1', $payload['data']['messageid']);
    }

    public function test_send_file_delegates_to_gateway(): void
    {
        $user = AuthUser::factory()->create();
        $this->grantWhatsappManage($user);

        PlatformUazapiInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'token' => 'tok-601',
        ]);

        $this->actingAs($user, 'sanctum');

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('sendFile')
            ->once()
            ->with('tok-601', ['number' => '1', 'url' => 'https://file.test'])
            ->andReturn(['messageid' => 'm2']);

        $controller = new PlatformUazapiMessageController($gateway);
        $request = Mockery::mock(PlatformUazapiSendFileRequest::class);
        $request->shouldReceive('validated')->once()->andReturn(['number' => '1', 'url' => 'https://file.test']);

        $response = $controller->sendFile($request, 'tok-601');
        $payload = $response->getData(true);

        $this->assertSame('Arquivo enviado', $payload['message']);
        $this->assertSame('m2', $payload['data']['messageid']);
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
