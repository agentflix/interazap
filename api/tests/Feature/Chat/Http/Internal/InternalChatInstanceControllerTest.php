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

// ── by-webhook-token ─────────────────────────────────────────────────────────

it('GET /by-webhook-token retorna 200 com instância encontrada por webhook_token', function (): void {
    $instance = ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'webhook_token' => 'wh-token-abc',
        'provider' => 'uazapi',
        'status' => 'connected',
    ]);

    $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->getJson('/api/internal/chat/instances/by-webhook-token/wh-token-abc')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.instance_id', (string) $instance->id)
        ->assertJsonPath('data.tenant_id', (string) $this->tenant->id)
        ->assertJsonPath('data.provider', 'uazapi')
        ->assertJsonPath('data.status', 'connected');
});

it('GET /by-webhook-token faz fallback por settings_json token', function (): void {
    $instance = ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'webhook_token' => 'outro-token',
        'settings_json' => ['token' => 'settings-tok-xyz'],
    ]);

    $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->getJson('/api/internal/chat/instances/by-webhook-token/settings-tok-xyz')
        ->assertOk()
        ->assertJsonPath('data.instance_id', (string) $instance->id);
});

it('GET /by-webhook-token retorna 404 quando token não existe', function (): void {
    $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->getJson('/api/internal/chat/instances/by-webhook-token/nao-existe')
        ->assertNotFound();
});

it('GET /by-webhook-token retorna 401 sem Authorization', function (): void {
    $this->getJson('/api/internal/chat/instances/by-webhook-token/qualquer')
        ->assertStatus(401);
});

it('GET /by-webhook-token retorna 403 com bearer inválido', function (): void {
    $this->withHeaders(['Authorization' => 'Bearer errado'])
        ->getJson('/api/internal/chat/instances/by-webhook-token/qualquer')
        ->assertStatus(403);
});

// ── by-id ────────────────────────────────────────────────────────────────────

it('GET /instances/{id} retorna 200 com instância encontrada', function (): void {
    $instance = ChatInstance::factory()->create(['tenant_id' => (string) $this->tenant->id]);

    $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->getJson("/api/internal/chat/instances/{$instance->id}")
        ->assertOk()
        ->assertJsonPath('data.instance_id', (string) $instance->id)
        ->assertJsonPath('data.tenant_id', (string) $this->tenant->id);
});

it('GET /instances/{id} retorna 404 para id inexistente', function (): void {
    $fakeId = (string) \Illuminate\Support\Str::orderedUuid();

    $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->getJson("/api/internal/chat/instances/{$fakeId}")
        ->assertNotFound();
});

it('GET /instances/{id} retorna 401 sem Authorization', function (): void {
    $fakeId = (string) \Illuminate\Support\Str::orderedUuid();

    $this->getJson("/api/internal/chat/instances/{$fakeId}")
        ->assertStatus(401);
});

// ── PATCH connection-status ───────────────────────────────────────────────────

it('PATCH /connection-status atualiza status e last_status_at', function (): void {
    $instance = ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'status' => 'disconnected',
    ]);

    $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->patchJson("/api/internal/chat/instances/{$instance->id}/connection-status", [
            'status' => 'connected',
            'connected_at' => '2026-05-24T10:00:00Z',
        ])
        ->assertOk()
        ->assertJson(['updated' => true]);

    $instance->refresh();
    expect($instance->status)->toBe('connected')
        ->and($instance->last_status_at)->not->toBeNull();
});

it('PATCH /connection-status funciona sem connected_at', function (): void {
    $instance = ChatInstance::factory()->create(['tenant_id' => (string) $this->tenant->id]);

    $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->patchJson("/api/internal/chat/instances/{$instance->id}/connection-status", [
            'status' => 'disconnected',
        ])
        ->assertOk()
        ->assertJson(['updated' => true]);

    expect($instance->fresh()->status)->toBe('disconnected');
});

it('PATCH /connection-status retorna 404 para id inexistente', function (): void {
    $fakeId = (string) \Illuminate\Support\Str::orderedUuid();

    $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->patchJson("/api/internal/chat/instances/{$fakeId}/connection-status", [
            'status' => 'connected',
        ])
        ->assertNotFound();
});

it('PATCH /connection-status retorna 401 sem Authorization', function (): void {
    $fakeId = (string) \Illuminate\Support\Str::orderedUuid();

    $this->patchJson("/api/internal/chat/instances/{$fakeId}/connection-status", [
        'status' => 'connected',
    ])->assertStatus(401);
});

// ── index (listing) ──────────────────────────────────────────────────────────

it('GET /instances lista instâncias do tenant', function (): void {
    $other = PlatformTenant::factory()->create();
    ChatInstance::factory()->count(3)->create(['tenant_id' => (string) $this->tenant->id]);
    ChatInstance::factory()->count(2)->create(['tenant_id' => (string) $other->id]);

    $response = $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->getJson("/api/internal/chat/instances?tenant_id={$this->tenant->id}");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
    foreach ($response->json('data') as $item) {
        expect($item['tenant_id'])->toBe((string) $this->tenant->id);
    }
});

it('GET /instances filtra por provider', function (): void {
    ChatInstance::factory()->create(['tenant_id' => (string) $this->tenant->id, 'provider' => 'uazapi']);
    ChatInstance::factory()->create(['tenant_id' => (string) $this->tenant->id, 'provider' => 'meta']);

    $response = $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->getJson("/api/internal/chat/instances?tenant_id={$this->tenant->id}&provider=uazapi");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.provider'))->toBe('uazapi');
});

it('GET /instances filtra por status', function (): void {
    ChatInstance::factory()->create(['tenant_id' => (string) $this->tenant->id, 'status' => 'connected']);
    ChatInstance::factory()->create(['tenant_id' => (string) $this->tenant->id, 'status' => 'disconnected']);

    $response = $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->getJson("/api/internal/chat/instances?tenant_id={$this->tenant->id}&status=connected");

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
});

it('GET /instances retorna 422 sem tenant_id', function (): void {
    $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->getJson('/api/internal/chat/instances')
        ->assertStatus(422);
});

it('GET /instances retorna 401 sem Authorization', function (): void {
    $this->getJson("/api/internal/chat/instances?tenant_id={$this->tenant->id}")
        ->assertStatus(401);
});
