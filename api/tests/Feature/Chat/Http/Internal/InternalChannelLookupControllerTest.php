<?php

declare(strict_types=1);

use Domain\Chat\Models\ChatInstance;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.gateway.secret', 'gw-secret');
    $this->tenant = PlatformTenant::factory()->create();
});

it('GET /api/internal/chat/instances/by-waba/{wabaId} retorna 200 com tenant + instance', function (): void {
    $instance = ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'meta',
        'is_active' => true,
        'settings_json' => ['waba_id' => 'WABA-OK'],
    ]);

    $response = $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->getJson('/api/internal/chat/instances/by-waba/WABA-OK');

    $response->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.tenant_id', (string) $this->tenant->id)
        ->assertJsonPath('data.instance_id', (string) $instance->id);
});

it('retorna 404 quando waba_id não existe', function (): void {
    $response = $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->getJson('/api/internal/chat/instances/by-waba/NAO-EXISTE');

    $response->assertNotFound()
        ->assertJsonPath('success', false);
});

it('retorna 401 sem header Authorization', function (): void {
    $this->getJson('/api/internal/chat/instances/by-waba/WABA-OK')
        ->assertStatus(401);
});

it('retorna 403 com bearer token inválido', function (): void {
    $this->withHeaders(['Authorization' => 'Bearer errado'])
        ->getJson('/api/internal/chat/instances/by-waba/WABA-OK')
        ->assertStatus(403);
});
