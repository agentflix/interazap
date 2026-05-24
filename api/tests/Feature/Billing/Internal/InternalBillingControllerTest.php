<?php

declare(strict_types=1);

use Domain\Billing\Models\BillingWebhookEvent;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.gateway.api_key', 'test-internal-key');
    $this->tenant = PlatformTenant::factory()->create();
});

// ── GET tenants/by-webhook-token ────────────────────────────────────────────

it('GET /by-webhook-token resolve tenant pelo billing_webhook_token', function (): void {
    $this->tenant->update(['billing_webhook_token' => 'bill-token-abc']);

    $this->withHeaders(['X-Internal-Api-Key' => 'test-internal-key'])
        ->getJson('/api/internal/billing/tenants/by-webhook-token/bill-token-abc')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.tenant_id', (string) $this->tenant->id)
        ->assertJsonStructure(['data' => ['tenant_id', 'plan', 'quota_monthly', 'overflow_mode']]);
});

it('GET /by-webhook-token retorna 404 para token inexistente', function (): void {
    $this->withHeaders(['X-Internal-Api-Key' => 'test-internal-key'])
        ->getJson('/api/internal/billing/tenants/by-webhook-token/nao-existe')
        ->assertNotFound();
});

it('GET /by-webhook-token retorna 401 sem header', function (): void {
    $this->getJson('/api/internal/billing/tenants/by-webhook-token/qualquer')
        ->assertUnauthorized();
});

it('GET /by-webhook-token retorna 401 com key inválida', function (): void {
    $this->withHeaders(['X-Internal-Api-Key' => 'errada'])
        ->getJson('/api/internal/billing/tenants/by-webhook-token/qualquer')
        ->assertUnauthorized();
});

// ── POST webhook-events ──────────────────────────────────────────────────────

it('POST /webhook-events cria evento de billing', function (): void {
    $response = $this->withHeaders(['X-Internal-Api-Key' => 'test-internal-key'])
        ->postJson('/api/internal/billing/webhook-events', [
            'tenant_id'   => (string) $this->tenant->id,
            'event_type'  => 'PAYMENT_RECEIVED',
            'payload'     => ['amount' => 100],
            'external_id' => 'ext-abc-123',
        ]);

    $response->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['data' => ['event_id', 'created_at']]);

    $this->assertDatabaseHas('shared_webhook_events', [
        'tenant_id'         => (string) $this->tenant->id,
        'event_type'        => 'PAYMENT_RECEIVED',
        'provider_event_id' => 'ext-abc-123',
        'domain'            => 'billing',
    ]);
});

it('POST /webhook-events cria evento sem external_id', function (): void {
    $response = $this->withHeaders(['X-Internal-Api-Key' => 'test-internal-key'])
        ->postJson('/api/internal/billing/webhook-events', [
            'tenant_id'  => (string) $this->tenant->id,
            'event_type' => 'PAYMENT_OVERDUE',
            'payload'    => ['invoice_id' => 'inv-001'],
        ]);

    $response->assertCreated()
        ->assertJsonPath('data.event_id', fn (string $id) => Str::isUuid($id));
});

it('POST /webhook-events retorna 401 sem header', function (): void {
    $this->postJson('/api/internal/billing/webhook-events', [
        'tenant_id'  => (string) $this->tenant->id,
        'event_type' => 'X',
        'payload'    => [],
    ])->assertUnauthorized();
});

it('POST /webhook-events retorna 422 com payload inválido', function (): void {
    $this->withHeaders(['X-Internal-Api-Key' => 'test-internal-key'])
        ->postJson('/api/internal/billing/webhook-events', ['event_type' => 'X'])
        ->assertUnprocessable();
});

// ── PATCH webhook-events/{id}/stream-id ─────────────────────────────────────

it('PATCH /stream-id atualiza stream_id de evento existente', function (): void {
    $event = BillingWebhookEvent::create([
        'id'         => (string) Str::orderedUuid(),
        'tenant_id'  => (string) $this->tenant->id,
        'event_type' => 'PAYMENT_RECEIVED',
        'payload'    => ['amount' => 50],
    ]);

    $this->withHeaders(['X-Internal-Api-Key' => 'test-internal-key'])
        ->patchJson("/api/internal/billing/webhook-events/{$event->id}/stream-id", [
            'stream_id' => '1234567890-0',
        ])
        ->assertOk()
        ->assertJsonPath('data.updated', true);

    $this->assertDatabaseHas('shared_webhook_events', [
        'id'        => $event->id,
        'stream_id' => '1234567890-0',
    ]);
});

it('PATCH /stream-id retorna 404 para evento inexistente', function (): void {
    $fakeId = (string) Str::orderedUuid();

    $this->withHeaders(['X-Internal-Api-Key' => 'test-internal-key'])
        ->patchJson("/api/internal/billing/webhook-events/{$fakeId}/stream-id", [
            'stream_id' => '1234567890-0',
        ])
        ->assertNotFound();
});

it('PATCH /stream-id retorna 401 sem header', function (): void {
    $this->patchJson('/api/internal/billing/webhook-events/'.Str::orderedUuid().'/stream-id', [
        'stream_id' => '1234567890-0',
    ])->assertUnauthorized();
});

it('PATCH /stream-id retorna 422 sem stream_id', function (): void {
    $this->withHeaders(['X-Internal-Api-Key' => 'test-internal-key'])
        ->patchJson('/api/internal/billing/webhook-events/'.Str::orderedUuid().'/stream-id', [])
        ->assertUnprocessable();
});
