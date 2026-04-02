<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Actions\PlatformUazapiInstanceActions;
use Domain\Platform\Http\Controllers\PlatformUazapiInstanceController;
use Domain\Platform\Http\Requests\PlatformUazapiConnectRequest;
use Domain\Platform\Http\Requests\PlatformUazapiInstanceRequest;
use Domain\Platform\Models\PlatformTenant;
use Domain\Platform\Models\PlatformUazapiInstance;
use Domain\Platform\Services\UazapiGatewayService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class PlatformUazapiInstanceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function createAuthorizedUser(string $tenantId): AuthUser
    {
        $user = AuthUser::factory()->create(['tenant_id' => $tenantId]);

        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'whatsapp.manage', 'guard_name' => 'sanctum'],
            ['id' => Str::orderedUuid()],
        );

        $user->givePermissionTo($permission);

        return $user;
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_index_returns_paginated_resources(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = $this->createAuthorizedUser($tenant->id);
        $this->actingAs($user, 'sanctum');
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Instance',
            'system_name' => 'uazapi',
            'token' => 'tok-idx',
            'status' => 'connected',
        ]);

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $actions = new PlatformUazapiInstanceActions($gateway);

        $controller = new PlatformUazapiInstanceController($actions);
        $request = Request::create('/instances', 'GET', ['status' => 'connected']);
        $request->setUserResolver(fn (): \Domain\Auth\Models\AuthUser => $user);

        $response = $controller->index($request);
        $payload = $response->getData(true);

        $this->assertTrue($payload['success']);
        $this->assertSame('Instâncias listadas', $payload['message']);
        $this->assertSame($instance->id, $payload['data'][0]['id']);
    }

    public function test_store_creates_instance_and_returns_resource(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = $this->createAuthorizedUser($tenant->id);
        $this->actingAs($user, 'sanctum');
        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('initInstance')
            ->once()
            ->with(Mockery::type('array'))
            ->andReturn([
                'name' => 'Created',
                'token' => 'tok-store',
                'webhook' => ['url' => 'https://hook.test'],
            ]);

        $actions = new PlatformUazapiInstanceActions($gateway);

        $controller = new PlatformUazapiInstanceController($actions);
        $request = Mockery::mock(PlatformUazapiInstanceRequest::class);
        $request->shouldReceive('validated')->once()->andReturn([
            'name' => 'Created',
            'system_name' => 'uazapi',
        ]);
        $request->shouldReceive('user')->once()->andReturn($user);

        $response = $controller->store($request);
        $payload = $response->getData(true);

        $instance = PlatformUazapiInstance::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('Instância criada', $payload['message']);
        $this->assertSame($instance->id, $payload['data']['id']);
    }

    public function test_show_returns_instance_resource(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = $this->createAuthorizedUser($tenant->id);
        $this->actingAs($user, 'sanctum');
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Show',
            'system_name' => 'uazapi',
            'token' => 'tok-show',
            'status' => 'connected',
        ]);

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $actions = new PlatformUazapiInstanceActions($gateway);

        $controller = new PlatformUazapiInstanceController($actions);
        $request = Request::create('/instances/'.$instance->id, 'GET');
        $request->setUserResolver(fn (): \Domain\Auth\Models\AuthUser => $user);

        $response = $controller->show($request, $instance->id);
        $payload = $response->getData(true);

        $this->assertSame('Instância carregada', $payload['message']);
        $this->assertSame($instance->id, $payload['data']['id']);
    }

    public function test_status_returns_gateway_status(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = $this->createAuthorizedUser($tenant->id);
        $this->actingAs($user, 'sanctum');
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Status',
            'system_name' => 'uazapi',
            'token' => 'tok-status',
            'status' => 'connected',
        ]);

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('status')
            ->once()
            ->with('tok-status')
            ->andReturn(['status' => 'connected']);

        $actions = new PlatformUazapiInstanceActions($gateway);

        $controller = new PlatformUazapiInstanceController($actions);
        $request = Request::create('/instances/'.$instance->id.'/status', 'GET');
        $request->setUserResolver(fn (): \Domain\Auth\Models\AuthUser => $user);

        $response = $controller->status($request, $instance->id);
        $payload = $response->getData(true);

        $this->assertSame('Status da instância', $payload['message']);
        $this->assertSame('connected', $payload['data']['status']);
    }

    public function test_connect_returns_instance_and_connection_payload(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = $this->createAuthorizedUser($tenant->id);
        $this->actingAs($user, 'sanctum');
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Connect',
            'system_name' => 'uazapi',
            'token' => 'tok-connect',
            'status' => 'disconnected',
        ]);

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('connectInstance')
            ->once()
            ->with('tok-connect', ['mode' => 'qr'])
            ->andReturn(['qr' => 'code', 'status' => 'connected']);

        $actions = new PlatformUazapiInstanceActions($gateway);

        $controller = new PlatformUazapiInstanceController($actions);
        $request = Mockery::mock(PlatformUazapiConnectRequest::class);
        $request->shouldReceive('validated')->once()->andReturn(['mode' => 'qr']);
        $request->shouldReceive('user')->once()->andReturn($user);

        $response = $controller->connect($request, $instance->id);
        $payload = $response->getData(true);

        $this->assertSame('Conexão iniciada', $payload['message']);
        $this->assertSame('code', $payload['data']['connection']['qr']);
        $this->assertSame($instance->id, $payload['data']['instance']['id']);
    }

    public function test_disconnect_returns_success_payload(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = $this->createAuthorizedUser($tenant->id);
        $this->actingAs($user, 'sanctum');
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Disconnect',
            'system_name' => 'uazapi',
            'token' => 'tok-disconnect',
            'status' => 'connected',
        ]);

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('disconnectInstance')
            ->once()
            ->with('tok-disconnect')
            ->andReturn(['status' => 'disconnected']);

        $actions = new PlatformUazapiInstanceActions($gateway);

        $controller = new PlatformUazapiInstanceController($actions);
        $request = Request::create('/instances/'.$instance->id.'/disconnect', 'POST');
        $request->setUserResolver(fn (): \Domain\Auth\Models\AuthUser => $user);

        $response = $controller->disconnect($request, $instance->id);
        $payload = $response->getData(true);

        $this->assertSame('Instância desconectada', $payload['message']);
        $this->assertSame('disconnected', $payload['data']['status']);
    }

    public function test_destroy_returns_no_content(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = $this->createAuthorizedUser($tenant->id);
        $this->actingAs($user, 'sanctum');
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Destroy',
            'system_name' => 'uazapi',
            'token' => 'tok-destroy',
            'status' => 'connected',
        ]);

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('deleteInstance')
            ->once()
            ->with('tok-destroy')
            ->andReturn(['deleted' => true]);

        $actions = new PlatformUazapiInstanceActions($gateway);

        $controller = new PlatformUazapiInstanceController($actions);
        $request = Request::create('/instances/'.$instance->id, 'DELETE');
        $request->setUserResolver(fn (): \Domain\Auth\Models\AuthUser => $user);

        $response = $controller->destroy($request, $instance->id);

        $this->assertSame(204, $response->getStatusCode());
    }
}
