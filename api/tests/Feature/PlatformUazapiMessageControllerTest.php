<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformUazapiInstance;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlatformUazapiMessageControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.uazapi.base_url', 'https://free.uazapi.com');
        config()->set('services.uazapi.admin_token', 'admin-token');
    }

    public function test_send_text_uses_uazapi_adapter(): void
    {
        Http::fake([
            'https://free.uazapi.com/send/text' => Http::response(['ok' => true], 200),
        ]);

        $user = AuthUser::factory()->create();
        $this->grantWhatsappManage($user);

        PlatformUazapiInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'token' => 'token-xyz',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/platform/uazapi/instances/token-xyz/messages/text', [
                'number' => '55119999999',
                'text' => 'hello world',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Mensagem enviada',
            ]);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://free.uazapi.com/send/text'
            && $request->hasHeader('token', 'token-xyz')
            && $request['text'] === 'hello world');
    }

    public function test_send_file_uses_uazapi_adapter(): void
    {
        Http::fake([
            'https://free.uazapi.com/send/file' => Http::response(['ok' => true], 200),
        ]);

        $user = AuthUser::factory()->create();
        $this->grantWhatsappManage($user);

        PlatformUazapiInstance::factory()->create([
            'tenant_id' => $user->tenant_id,
            'token' => 'token-abc',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/platform/uazapi/instances/token-abc/messages/file', [
                'number' => '5511988877766',
                'url' => 'https://cdn.test/file.png',
                'caption' => 'logo',
            ])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'message' => 'Arquivo enviado',
            ]);

        Http::assertSent(fn ($request): bool => $request->url() === 'https://free.uazapi.com/send/file'
            && $request->hasHeader('token', 'token-abc')
            && $request['url'] === 'https://cdn.test/file.png');
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
