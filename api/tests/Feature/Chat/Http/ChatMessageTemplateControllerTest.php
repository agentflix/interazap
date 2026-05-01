<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessageTemplate;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config()->set('gateway.url', 'http://gateway.test');
    config()->set('gateway.secret', 'secret-key');
});

function makeManagerUser(): AuthUser
{
    $user = AuthUser::factory()->create();

    $perm = AuthPermission::query()->firstOrCreate(
        ['name' => 'chat.templates.manage', 'guard_name' => 'sanctum'],
        ['id' => (string) Str::orderedUuid()]
    );
    $user->givePermissionTo($perm);

    return $user;
}

it('lista templates do tenant com filtros', function (): void {
    $user = makeManagerUser();
    $instance = ChatInstance::factory()->create(['tenant_id' => $user->tenant_id, 'provider' => 'meta']);

    ChatMessageTemplate::factory()->create([
        'tenant_id' => $user->tenant_id,
        'chat_instance_id' => $instance->id,
        'provider' => 'meta',
        'name' => 'welcome_promo',
        'status' => 'approved',
        'language' => 'pt_BR',
    ]);
    ChatMessageTemplate::factory()->create([
        'tenant_id' => $user->tenant_id,
        'chat_instance_id' => $instance->id,
        'provider' => 'meta',
        'name' => 'reminder',
        'status' => 'pending',
        'language' => 'pt_BR',
    ]);
    ChatMessageTemplate::factory()->create([
        'tenant_id' => $user->tenant_id,
        'provider' => 'local',
        'name' => 'local_other',
    ]);

    $this->actingAs($user, 'sanctum');

    $resp = $this->getJson('/api/chat/message-templates?status=approved&search=welcome')
        ->assertOk()
        ->json();

    expect($resp['data'])->toHaveCount(1)
        ->and($resp['data'][0]['name'])->toBe('welcome_promo');

    $this->getJson('/api/chat/message-templates?chat_instance_id='.$instance->id)
        ->assertOk()
        ->assertJsonCount(2, 'data');
});

it('store cria template e expõe campos completos', function (): void {
    $user = makeManagerUser();
    $this->actingAs($user, 'sanctum');

    $resp = $this->postJson('/api/chat/message-templates', [
        'name' => 'novo_template',
        'shortcut' => '/novo',
        'content' => 'Olá {{1}}',
        'language' => 'pt_BR',
        'category' => 'support',
        'is_active' => true,
        'provider' => 'local',
    ])->assertCreated()->json();

    expect($resp['data']['name'])->toBe('novo_template')
        ->and($resp['data']['provider'])->toBe('local')
        ->and($resp['data'])->toHaveKeys([
            'id', 'shortcut', 'content', 'category',
            'is_active', 'provider', 'language', 'status', 'components',
        ]);
});

it('show retorna template do tenant', function (): void {
    $user = makeManagerUser();
    $template = ChatMessageTemplate::factory()->create(['tenant_id' => $user->tenant_id]);

    $this->actingAs($user, 'sanctum');

    $this->getJson('/api/chat/message-templates/'.$template->id)
        ->assertOk()
        ->assertJsonPath('data.id', $template->id);
});

it('update edita template local', function (): void {
    $user = makeManagerUser();
    $template = ChatMessageTemplate::factory()->create([
        'tenant_id' => $user->tenant_id,
        'provider' => 'local',
        'name' => 'antigo',
    ]);

    $this->actingAs($user, 'sanctum');

    $this->putJson('/api/chat/message-templates/'.$template->id, [
        'name' => 'novo_nome',
        'content' => 'novo conteudo',
    ])->assertOk()->assertJsonPath('data.name', 'novo_nome');
});

it('update bloqueia campos proibidos em template Meta com 422', function (): void {
    $user = makeManagerUser();
    $instance = ChatInstance::factory()->create(['tenant_id' => $user->tenant_id, 'provider' => 'meta']);
    $template = ChatMessageTemplate::factory()->create([
        'tenant_id' => $user->tenant_id,
        'chat_instance_id' => $instance->id,
        'provider' => 'meta',
        'name' => 'meta_tpl',
        'language' => 'pt_BR',
    ]);

    $this->actingAs($user, 'sanctum');

    $this->putJson('/api/chat/message-templates/'.$template->id, [
        'name' => 'tentativa',
        'content' => 'novo',
    ])->assertStatus(422);
});

it('destroy remove template local', function (): void {
    $user = makeManagerUser();
    $template = ChatMessageTemplate::factory()->create([
        'tenant_id' => $user->tenant_id,
        'provider' => 'local',
    ]);

    $this->actingAs($user, 'sanctum');

    $this->deleteJson('/api/chat/message-templates/'.$template->id)
        ->assertNoContent();

    expect(ChatMessageTemplate::query()->find($template->id))->toBeNull();
});

it('destroy chama Gateway DELETE para template Meta', function (): void {
    Http::fake([
        '*/channels/*/templates/*' => Http::response(['success' => true], 200),
    ]);

    $user = makeManagerUser();
    $instance = ChatInstance::factory()->create(['tenant_id' => $user->tenant_id, 'provider' => 'meta']);
    $template = ChatMessageTemplate::factory()->create([
        'tenant_id' => $user->tenant_id,
        'chat_instance_id' => $instance->id,
        'provider' => 'meta',
        'name' => 'meta_tpl',
        'language' => 'pt_BR',
    ]);

    $this->actingAs($user, 'sanctum');

    $this->deleteJson('/api/chat/message-templates/'.$template->id)
        ->assertNoContent();

    Http::assertSent(fn ($req) => $req->method() === 'DELETE'
        && str_contains($req->url(), '/channels/'.$instance->id.'/templates/meta_tpl'));
});

it('sync executa SyncMetaTemplatesAction e retorna count', function (): void {
    Http::fake([
        '*/channels/*/templates*' => Http::response([
            'data' => [
                ['name' => 'a', 'language' => 'pt_BR', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'A']]],
                ['name' => 'b', 'language' => 'pt_BR', 'status' => 'APPROVED', 'components' => [['type' => 'BODY', 'text' => 'B']]],
            ],
        ], 200),
    ]);

    $user = makeManagerUser();
    $instance = ChatInstance::factory()->create(['tenant_id' => $user->tenant_id, 'provider' => 'meta']);

    $this->actingAs($user, 'sanctum');

    $this->postJson('/api/chat/message-templates/sync', [
        'chat_instance_id' => $instance->id,
    ])->assertOk()->assertJsonPath('data.count', 2);
});

it('retorna 403 ao criar sem permissão', function (): void {
    $user = AuthUser::factory()->create();

    AuthPermission::query()->firstOrCreate(
        ['name' => 'chat.templates.manage', 'guard_name' => 'sanctum'],
        ['id' => (string) Str::orderedUuid()]
    );

    $this->actingAs($user, 'sanctum');

    $this->postJson('/api/chat/message-templates', [
        'name' => 'teste',
        'content' => 'algo',
        'language' => 'pt_BR',
    ])->assertForbidden();
});

it('retorna 403 ao acessar template de outro tenant', function (): void {
    $user = makeManagerUser();

    $otherTenant = PlatformTenant::factory()->create();
    $otherTemplate = ChatMessageTemplate::factory()->create(['tenant_id' => $otherTenant->id]);

    $this->actingAs($user, 'sanctum');

    // Como TenantScope filtra por tenant_id, o registro de outro tenant não é
    // visível no `findOrFail`. Aceita 403 (autorização) ou 404 (escopo).
    $resp = $this->getJson('/api/chat/message-templates/'.$otherTemplate->id);
    expect($resp->status())->toBeIn([403, 404]);

    $resp = $this->putJson('/api/chat/message-templates/'.$otherTemplate->id, [
        'name' => 'hack',
    ]);
    expect($resp->status())->toBeIn([403, 404]);

    $resp = $this->deleteJson('/api/chat/message-templates/'.$otherTemplate->id);
    expect($resp->status())->toBeIn([403, 404]);
});
