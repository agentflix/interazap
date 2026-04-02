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

/**
 * Teste de integração com gateway fake.
 */
class PlatformUazapiInstanceE2eTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.gateway.url' => 'http://gateway.test']);
        Http::fake([
            'http://gateway.test/uazapi/instances' => Http::response([
                'name' => 'inst-e2e',
                'token' => 'token-e2e',
                'status' => 'disconnected',
                'webhook' => [
                    'url' => 'https://free.uazapi.com/webhook',
                ],
            ], 200),
            'http://gateway.test/uazapi/instances/token-e2e/status' => Http::response([
                'instance' => ['status' => 'connected'],
            ], 200),
            'http://gateway.test/uazapi/instances/token-e2e/disconnect' => Http::response(['status' => 'disconnected'], 200),
        ]);
    }

    private function acting(): void
    {
        $user = AuthUser::factory()->create(['password' => Hash::make('secret123')]);
        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'whatsapp.manage', 'guard_name' => 'sanctum'],
            ['id' => Str::orderedUuid()],
        );
        $user->givePermissionTo($permission);
        Sanctum::actingAs($user, abilities: ['*']);
    }

    public function test_e2e_create_status_disconnect(): void
    {
        $this->acting();

        // Cria instância via gateway fake
        $created = $this->postJson('/api/platform/uazapi/instances', [
            'name' => 'e2e-'.uniqid(),
        ])->assertStatus(201)->json('data');

        // Token is no longer exposed for security reasons, check has_token flag instead
        $this->assertTrue($created['has_token']);

        // Consulta status
        $this->getJson('/api/platform/uazapi/instances/'.$created['id'].'/status')
            ->assertStatus(200);

        // Desconecta (idempotente)
        $this->postJson('/api/platform/uazapi/instances/'.$created['id'].'/disconnect')
            ->assertStatus(200);
    }
}
