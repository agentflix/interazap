<?php

declare(strict_types=1);

use Domain\Ai\Models\AiAutopilotRun;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config()->set('services.gateway.secret', 'gw-secret');
    $this->tenant = PlatformTenant::factory()->create();
});

// ── ticket rooms ─────────────────────────────────────────────────────────────

it('GET /room-access permite acesso a ticket room do próprio tenant', function (): void {
    $ticket = ChatTicket::factory()->create(['tenant_id' => (string) $this->tenant->id]);

    $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->getJson("/api/internal/realtime/room-access?room=ticket:{$ticket->id}&tenant_id={$this->tenant->id}")
        ->assertOk()
        ->assertJson(['allowed' => true]);
});

it('GET /room-access nega acesso a ticket room de outro tenant', function (): void {
    $other = PlatformTenant::factory()->create();
    $ticket = ChatTicket::factory()->create(['tenant_id' => (string) $this->tenant->id]);

    $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->getJson("/api/internal/realtime/room-access?room=ticket:{$ticket->id}&tenant_id={$other->id}")
        ->assertOk()
        ->assertJson(['allowed' => false]);
});

it('GET /room-access nega acesso a ticket room inexistente', function (): void {
    $fakeId = (string) \Illuminate\Support\Str::orderedUuid();

    $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->getJson("/api/internal/realtime/room-access?room=ticket:{$fakeId}&tenant_id={$this->tenant->id}")
        ->assertOk()
        ->assertJson(['allowed' => false]);
});

// ── run rooms ────────────────────────────────────────────────────────────────

it('GET /room-access permite acesso a run room do próprio tenant', function (): void {
    $run = AiAutopilotRun::factory()->create(['tenant_id' => (string) $this->tenant->id]);

    $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->getJson("/api/internal/realtime/room-access?room=run:{$run->id}&tenant_id={$this->tenant->id}")
        ->assertOk()
        ->assertJson(['allowed' => true]);
});

it('GET /room-access nega acesso a run room de outro tenant', function (): void {
    $other = PlatformTenant::factory()->create();
    $run = AiAutopilotRun::factory()->create(['tenant_id' => (string) $this->tenant->id]);

    $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->getJson("/api/internal/realtime/room-access?room=run:{$run->id}&tenant_id={$other->id}")
        ->assertOk()
        ->assertJson(['allowed' => false]);
});

// ── auth guards ───────────────────────────────────────────────────────────────

it('GET /room-access retorna 401 sem Authorization', function (): void {
    $ticket = ChatTicket::factory()->create(['tenant_id' => (string) $this->tenant->id]);

    $this->getJson("/api/internal/realtime/room-access?room=ticket:{$ticket->id}&tenant_id={$this->tenant->id}")
        ->assertStatus(401);
});

it('GET /room-access retorna 403 com bearer inválido', function (): void {
    $ticket = ChatTicket::factory()->create(['tenant_id' => (string) $this->tenant->id]);

    $this->withHeaders(['Authorization' => 'Bearer errado'])
        ->getJson("/api/internal/realtime/room-access?room=ticket:{$ticket->id}&tenant_id={$this->tenant->id}")
        ->assertStatus(403);
});

it('GET /room-access retorna 422 sem room', function (): void {
    $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->getJson("/api/internal/realtime/room-access?tenant_id={$this->tenant->id}")
        ->assertStatus(422);
});

it('GET /room-access retorna 422 sem tenant_id', function (): void {
    $fakeId = (string) \Illuminate\Support\Str::orderedUuid();

    $this->withHeaders(['Authorization' => 'Bearer gw-secret'])
        ->getJson("/api/internal/realtime/room-access?room=ticket:{$fakeId}")
        ->assertStatus(422);
});
