<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PlatformUazapiInstanceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    private function acting(): array
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'whatsapp.manage', 'guard_name' => 'sanctum'],
            ['id' => Str::orderedUuid()],
        );
        $user->givePermissionTo($permission);
        Sanctum::actingAs($user, abilities: ['*']);

        return [$user, $user->tenant_id];
    }

    public function test_crud_instance_and_status(): void
    {
        [$user, $tenantId] = $this->acting();
        Http::fake([
            'http://gateway.test/uazapi/instances' => Http::response([
                'name' => 'inst-1',
                'token' => 'token123',
                'status' => 'disconnected',
                'webhook' => [
                    'url' => 'https://free.uazapi.com/webhook',
                ],
            ], 200),
            'http://gateway.test/uazapi/instances/token123/status' => Http::response([
                'instance' => ['status' => 'connected'],
            ], 200),
            'http://gateway.test/uazapi/instances/token123/connect' => Http::response(['status' => 'connecting'], 200),
            'http://gateway.test/uazapi/instances/token123/disconnect' => Http::response(['status' => 'disconnected'], 200),
            'http://gateway.test/uazapi/instances/token123/delete' => Http::response([], 200),
        ]);
        config(['services.gateway.url' => 'http://gateway.test']);

        $created = $this->postJson('/api/platform/uazapi/instances', [
            'name' => 'inst-1',
        ])->assertStatus(201)->json('data');

        // Token is no longer exposed in response for security reasons
        $this->assertTrue($created['has_token']);
        $this->assertNotEmpty($created['token_preview']);
        $this->assertDatabaseHas('platform_uazapi_instances', [
            'id' => $created['id'],
            'webhook_url' => 'https://free.uazapi.com/webhook',
        ]);

        $this->getJson('/api/platform/uazapi/instances')->assertStatus(200);

        $this->getJson('/api/platform/uazapi/instances/'.$created['id'].'/status')
            ->assertStatus(200);

        $this->postJson('/api/platform/uazapi/instances/'.$created['id'].'/connect', [])
            ->assertStatus(200);

        $this->postJson('/api/platform/uazapi/instances/'.$created['id'].'/disconnect')
            ->assertStatus(200);

        $this->deleteJson('/api/platform/uazapi/instances/'.$created['id'])->assertStatus(204);
        $this->assertDatabaseMissing('platform_uazapi_instances', ['id' => $created['id']]);
    }

    public function test_create_instance_requires_name(): void
    {
        [$user] = $this->acting();
        Http::fake(); // não deve ser chamado em validação

        $this->postJson('/api/platform/uazapi/instances', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_gateway_failure_does_not_persist_instance(): void
    {
        [$user] = $this->acting();
        config(['services.gateway.url' => 'http://gateway.test']);
        Http::fake([
            'http://gateway.test/uazapi/instances' => Http::response(['error' => 'down'], 500),
        ]);

        $response = $this->postJson('/api/platform/uazapi/instances', [
            'name' => 'inst-fail',
        ]);

        $response->assertStatus(500);
        $this->assertDatabaseMissing('platform_uazapi_instances', ['name' => 'inst-fail']);
    }
}
