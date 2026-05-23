<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use Domain\Platform\Actions\PlatformUazapiInstanceActions;
use Domain\Platform\DTOs\PlatformUazapiInstanceDTO;
use Domain\Platform\Models\PlatformTenant;
use Domain\Platform\Models\PlatformUazapiInstance;
use Domain\Platform\Services\UazapiGatewayService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery;
use Tests\TestCase;

class PlatformUazapiInstanceActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        try {
            Mockery::close();
        } catch (\Throwable) {
            // Mockery container já é resetado antes do throw; engole p/ garantir parent::tearDown roda.
        }
        parent::tearDown();
    }

    public function test_list_filters_by_status_and_search(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $otherTenant = PlatformTenant::factory()->create();

        PlatformUazapiInstance::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main',
            'system_name' => 'uazapi',
            'token' => 'tok-main',
            'status' => 'connected',
        ]);
        PlatformUazapiInstance::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Backup',
            'system_name' => 'other',
            'token' => 'tok-backup',
            'status' => 'disconnected',
        ]);
        PlatformUazapiInstance::query()->create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Other',
            'system_name' => 'uazapi',
            'token' => 'tok-other',
            'status' => 'connected',
        ]);

        $actions = new PlatformUazapiInstanceActions(Mockery::mock(UazapiGatewayService::class));
        $result = $actions->list((string) $tenant->id, [
            'status' => 'connected',
            'search' => 'Main',
        ]);

        $this->assertSame(1, $result->total());
        $this->assertSame('Main', $result->items()[0]->name);
    }

    public function test_create_persists_instance_with_gateway_response(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $gateway = Mockery::mock(UazapiGatewayService::class);

        $gateway->shouldReceive('initInstance')
            ->once()
            ->andReturn([
                'name' => 'Gateway Name',
                'token' => 'tok-1',
                'status' => ['connected' => true],
                'webhook' => ['url' => 'https://app.test/webhook'],
            ]);

        $actions = new PlatformUazapiInstanceActions($gateway);
        $dto = new PlatformUazapiInstanceDTO('Local Name', 'uazapi', []);

        $instance = $actions->create((string) $tenant->id, $dto);

        $this->assertSame('Gateway Name', $instance->name);
        $this->assertSame('tok-1', $instance->token);
        $this->assertSame('connected', $instance->status);
        $this->assertSame('https://app.test/webhook', $instance->webhook_url);
    }

    public function test_status_updates_instance_metadata(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Instance',
            'system_name' => 'uazapi',
            'token' => 'tok-2',
            'status' => 'disconnected',
        ]);

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('status')
            ->once()
            ->with('tok-2')
            ->andReturn(['status' => 'connected']);

        $actions = new PlatformUazapiInstanceActions($gateway);
        $response = $actions->status((string) $tenant->id, (string) $instance->id);

        $this->assertSame('connected', $response['status']);
        $this->assertSame('connected', $instance->fresh()->status);
        $this->assertNotNull($instance->fresh()->last_status_at);
    }

    public function test_connect_and_disconnect_update_status(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Instance',
            'system_name' => 'uazapi',
            'token' => 'tok-3',
            'status' => 'disconnected',
        ]);

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('connectInstance')
            ->once()
            ->with('tok-3', ['mode' => 'qr'])
            ->andReturn(['status' => 'connecting']);
        $gateway->shouldReceive('disconnectInstance')
            ->once()
            ->with('tok-3')
            ->andReturn(['status' => 'disconnected']);

        $actions = new PlatformUazapiInstanceActions($gateway);
        $actions->connect((string) $tenant->id, (string) $instance->id, ['mode' => 'qr']);
        $this->assertSame('connecting', $instance->fresh()->status);

        $actions->disconnect((string) $tenant->id, (string) $instance->id);
        $this->assertSame('disconnected', $instance->fresh()->status);
    }

    public function test_delete_removes_instance_and_calls_gateway(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Instance',
            'system_name' => 'uazapi',
            'token' => 'tok-4',
        ]);

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('deleteInstance')->once()->with('tok-4')->andReturn(['ok' => true]);

        $actions = new PlatformUazapiInstanceActions($gateway);
        $actions->delete((string) $tenant->id, (string) $instance->id);

        $this->assertDatabaseMissing('platform_uazapi_instances', ['id' => $instance->id]);
    }

    public function test_list_without_filters_uses_default_per_page(): void
    {
        $tenant = PlatformTenant::factory()->create();

        PlatformUazapiInstance::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Instance 1',
            'system_name' => 'uazapi',
            'token' => 'tok-100',
            'status' => 'connected',
        ]);

        PlatformUazapiInstance::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Instance 2',
            'system_name' => 'uazapi',
            'token' => 'tok-101',
            'status' => 'disconnected',
        ]);

        $actions = new PlatformUazapiInstanceActions(Mockery::mock(UazapiGatewayService::class));
        $result = $actions->list((string) $tenant->id);

        $this->assertSame(2, $result->total());
        $this->assertSame(15, $result->perPage());
    }

    public function test_create_uses_fallbacks_for_name_token_webhook_and_status(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $gateway = Mockery::mock(UazapiGatewayService::class);

        $gateway->shouldReceive('initInstance')
            ->once()
            ->andReturn([
                'instance' => [
                    'token' => 'tok-200',
                    'status' => ['loggedIn' => true],
                ],
                'webhook_url' => 'https://app.test/webhook-2',
            ]);

        $actions = new PlatformUazapiInstanceActions($gateway);
        $dto = new PlatformUazapiInstanceDTO('Fallback Name', 'uazapi', []);

        $instance = $actions->create((string) $tenant->id, $dto);

        $this->assertSame('Fallback Name', $instance->name);
        $this->assertSame('tok-200', $instance->token);
        $this->assertSame('connected', $instance->status);
        $this->assertSame('https://app.test/webhook-2', $instance->webhook_url);
    }

    public function test_status_uses_existing_status_when_gateway_payload_missing(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Instance',
            'system_name' => 'uazapi',
            'token' => 'tok-300',
            'status' => 'connected',
        ]);

        $gateway = Mockery::mock(UazapiGatewayService::class);
        $gateway->shouldReceive('status')
            ->once()
            ->with('tok-300')
            ->andReturn([]);

        $actions = new PlatformUazapiInstanceActions($gateway);
        $response = $actions->status((string) $tenant->id, (string) $instance->id);

        $this->assertSame([], $response);
        $this->assertSame('connected', $instance->fresh()->status);
    }

    public function test_find_scopes_by_tenant(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $instance = PlatformUazapiInstance::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Instance',
            'system_name' => 'uazapi',
            'token' => 'tok-400',
        ]);

        $actions = new PlatformUazapiInstanceActions(Mockery::mock(UazapiGatewayService::class));
        $found = $actions->find((string) $tenant->id, (string) $instance->id);

        $this->assertSame($instance->id, $found->id);
    }

    public function test_private_status_helpers_handle_bool_and_arrays(): void
    {
        $actions = new PlatformUazapiInstanceActions(Mockery::mock(UazapiGatewayService::class));

        $this->assertSame('connected', $this->callPrivate($actions, 'extractStatus', [['status' => true], 'disconnected']));
        $this->assertSame('disconnected', $this->callPrivate($actions, 'extractStatus', [['status' => false], 'connected']));
        $this->assertSame('connected', $this->callPrivate($actions, 'extractStatus', [['status' => ['status' => 'connected']], 'disconnected']));
        $this->assertSame('connected', $this->callPrivate($actions, 'extractStatus', [['status' => ['connected' => true]], 'disconnected']));
        $this->assertSame('connected', $this->callPrivate($actions, 'extractStatus', [['status' => ['loggedIn' => true]], 'disconnected']));
        $this->assertSame('fallback', $this->callPrivate($actions, 'extractStatus', [['status' => 123], 'fallback']));

        $this->assertSame('connected', $this->callPrivate($actions, 'normalizeStatus', ['connected']));
        $this->assertSame('connected', $this->callPrivate($actions, 'normalizeStatus', [true]));
        $this->assertSame('disconnected', $this->callPrivate($actions, 'normalizeStatus', [false]));
        $this->assertSame('connected', $this->callPrivate($actions, 'normalizeStatus', [['status' => 'connected']]));
        $this->assertSame('connected', $this->callPrivate($actions, 'normalizeStatus', [['connected' => true]]));
        $this->assertSame('connected', $this->callPrivate($actions, 'normalizeStatus', [['loggedIn' => true]]));
        $this->assertSame('disconnected', $this->callPrivate($actions, 'normalizeStatus', [['unknown' => 'value']]));
    }

    private function callPrivate(object $object, string $method, array $args = []): mixed
    {
        $reflection = new \ReflectionMethod($object, $method);

        return $reflection->invokeArgs($object, $args);
    }
}
