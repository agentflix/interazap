<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Actions\CloseInactiveTicketsAction;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatTicket;
use Domain\Configuration\Events\TicketClosedEvent;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

uses(LazilyRefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────

/**
 * Cria um tenant com configurações de auto-close habilitadas.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeTenantWithAutoClose(array $overrides = []): PlatformTenant
{
    return PlatformTenant::factory()->create(array_merge([
        'is_active' => true,
        'settings_chat' => [
            'auto_close_inactivity_enabled' => true,
            'auto_close_inactivity_minutes' => 30,
            'auto_close_inactivity_target' => 'both',
        ],
    ], $overrides));
}

/**
 * Cria uma instância ativa vinculada ao tenant.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeActiveInstance(PlatformTenant $tenant, array $overrides = []): ChatInstance
{
    return ChatInstance::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'is_active' => true,
    ], $overrides));
}

/**
 * Cria um ticket aberto vinculado ao tenant e instância.
 *
 * @param  array<string, mixed>  $overrides
 */
function makeOpenTicket(PlatformTenant $tenant, ChatInstance $instance, array $overrides = []): ChatTicket
{
    return ChatTicket::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'instance_id' => $instance->id,
        'status' => 'open',
        'last_message_at' => now()->subMinutes(60),
    ], $overrides));
}

// ── Cenário 1: Fecha tickets expirados (target=both) ──────────────

test('fecha tickets expirados com target both', function (): void {
    $tenant = makeTenantWithAutoClose();
    $instance = makeActiveInstance($tenant);
    $ticket = makeOpenTicket($tenant, $instance, [
        'last_message_at' => now()->subMinutes(60),
    ]);

    $action = app(CloseInactiveTicketsAction::class);
    $result = $action->execute($tenant);

    expect($result['closed_ids'])->toContain((string) $ticket->id);

    $ticket->refresh();
    expect($ticket->status)->toBe('closed');
    expect($ticket->closed_mode)->toBe('auto_inactivity');
    expect($ticket->closed_at)->not->toBeNull();
});

// ── Cenário 2: Não fecha tickets dentro do prazo ──────────────────

test('nao fecha tickets dentro do prazo de inatividade', function (): void {
    $tenant = makeTenantWithAutoClose([
        'settings_chat' => [
            'auto_close_inactivity_enabled' => true,
            'auto_close_inactivity_minutes' => 30,
            'auto_close_inactivity_target' => 'both',
        ],
    ]);
    $instance = makeActiveInstance($tenant);
    $ticket = makeOpenTicket($tenant, $instance, [
        'last_message_at' => now()->subMinutes(10),
    ]);

    $action = app(CloseInactiveTicketsAction::class);
    $result = $action->execute($tenant);

    expect($result['closed_ids'])->toBeEmpty();

    $ticket->refresh();
    expect($ticket->status)->toBe('open');
});

// ── Cenário 3: Respeita tenant isolation ──────────────────────────

test('respeita tenant isolation ao fechar apenas tickets do tenant processado', function (): void {
    $tenantA = makeTenantWithAutoClose();
    $tenantB = makeTenantWithAutoClose();

    $instanceA = makeActiveInstance($tenantA);
    $instanceB = makeActiveInstance($tenantB);

    $ticketA = makeOpenTicket($tenantA, $instanceA, [
        'last_message_at' => now()->subMinutes(60),
    ]);
    $ticketB = makeOpenTicket($tenantB, $instanceB, [
        'last_message_at' => now()->subMinutes(60),
    ]);

    // Executa apenas para o tenant A
    $action = app(CloseInactiveTicketsAction::class);
    $result = $action->execute($tenantA);

    expect($result['closed_ids'])->toContain((string) $ticketA->id);
    expect($result['closed_ids'])->not->toContain((string) $ticketB->id);

    $ticketA->refresh();
    $ticketB->refresh();

    expect($ticketA->status)->toBe('closed');
    expect($ticketB->status)->toBe('open');
});

// ── Cenário 4: Respeita config por canal ──────────────────────────
//
// ⚠️  BUG IDENTIFICADO: A action retorna early (linha 37-39) quando
//    auto_close_inactivity_enabled global é false, sem consultar os
//    canais. Isso impede que o override de canal prevaleça.
//    Este teste documenta o gap; a expectativa reflete o comportamento
//    CORRETO esperado, mas a implementação atual NÃO o satisfaz.

test('respeita config por canal quando global esta desabilitado', function (): void {
    // Tenant com auto-close DESABILITADO globalmente
    $tenant = PlatformTenant::factory()->create([
        'is_active' => true,
        'settings_chat' => [
            'auto_close_inactivity_enabled' => false,
            'auto_close_inactivity_minutes' => 30,
            'auto_close_inactivity_target' => 'both',
        ],
    ]);

    // Canal com override: auto_close habilitado, 15 minutos
    $instance = makeActiveInstance($tenant, [
        'auto_close_enabled' => true,
        'auto_close_after_minutes' => 15,
    ]);

    $ticket = makeOpenTicket($tenant, $instance, [
        'last_message_at' => now()->subMinutes(20),
    ]);

    $action = app(CloseInactiveTicketsAction::class);
    $result = $action->execute($tenant);

    // Override do canal prevalece sobre global disabled
    expect($result['closed_ids'])->toContain((string) $ticket->id);

    $ticket->refresh();
    expect($ticket->status)->toBe('closed');
    expect($ticket->closed_mode)->toBe('auto_inactivity');
});

// ── Cenário auxiliar: Canal com override prevalece quando global habilitado ──
//    (Este cenário funciona — o override de canal é respeitado quando o global
//     master switch está ON)

test('respeita config por canal quando global habilitado', function (): void {
    $tenant = PlatformTenant::factory()->create([
        'is_active' => true,
        'settings_chat' => [
            'auto_close_inactivity_enabled' => true,
            'auto_close_inactivity_minutes' => 30, // global: 30 min
            'auto_close_inactivity_target' => 'both',
        ],
    ]);

    // Canal com override: minutos = 10 (mais rigoroso que o global)
    $instance = makeActiveInstance($tenant, [
        'auto_close_after_minutes' => 10,
    ]);

    // Ticket com 15 min de inatividade: deve ser fechado pelo canal (10 min) mas
    // NÃO seria fechado se usasse o global (30 min)
    $ticket = makeOpenTicket($tenant, $instance, [
        'last_message_at' => now()->subMinutes(15),
    ]);

    $action = app(CloseInactiveTicketsAction::class);
    $result = $action->execute($tenant);

    expect($result['closed_ids'])->toContain((string) $ticket->id);
    $ticket->refresh();
    expect($ticket->status)->toBe('closed');
});

// ── Cenário 5: Respeita target 'client' ───────────────────────────

test('respeita target client usando last_customer_message_at', function (): void {
    $tenant = makeTenantWithAutoClose();

    $instance = makeActiveInstance($tenant, [
        'auto_close_target' => 'client',
        'auto_close_after_minutes' => 15,
    ]);

    // Cliente inativo há 30 min, mas atendente ativo há 5 min
    // Com target=client, deve fechar pois o cliente está inativo
    $ticket = makeOpenTicket($tenant, $instance, [
        'last_message_at' => now()->subMinutes(30),
        'last_customer_message_at' => now()->subMinutes(30),
        'last_agent_message_at' => now()->subMinutes(5),
    ]);

    $action = app(CloseInactiveTicketsAction::class);
    $result = $action->execute($tenant);

    expect($result['closed_ids'])->toContain((string) $ticket->id);

    $ticket->refresh();
    expect($ticket->status)->toBe('closed');
});

// ── Cenário 6: Respeita target 'agent' ────────────────────────────

test('respeita target agent usando last_agent_message_at', function (): void {
    $tenant = makeTenantWithAutoClose();

    $instance = makeActiveInstance($tenant, [
        'auto_close_target' => 'agent',
        'auto_close_after_minutes' => 15,
    ]);

    // Atendente inativo há 30 min, mas cliente ativo há 5 min
    // Com target=agent, deve fechar pois o atendente está inativo
    $ticket = makeOpenTicket($tenant, $instance, [
        'last_message_at' => now()->subMinutes(30),
        'last_customer_message_at' => now()->subMinutes(5),
        'last_agent_message_at' => now()->subMinutes(30),
    ]);

    $action = app(CloseInactiveTicketsAction::class);
    $result = $action->execute($tenant);

    expect($result['closed_ids'])->toContain((string) $ticket->id);

    $ticket->refresh();
    expect($ticket->status)->toBe('closed');
});

// ── Cenário 7: Idempotente ────────────────────────────────────────

test('execucao dupla nao fecha novamente tickets ja fechados', function (): void {
    $tenant = makeTenantWithAutoClose();
    $instance = makeActiveInstance($tenant);
    $ticket = makeOpenTicket($tenant, $instance, [
        'last_message_at' => now()->subMinutes(60),
    ]);

    $action = app(CloseInactiveTicketsAction::class);

    // Primeira execução: fecha o ticket
    $result1 = $action->execute($tenant);
    expect($result1['closed_ids'])->toContain((string) $ticket->id);

    $ticket->refresh();
    $closedAt = $ticket->closed_at;
    expect($closedAt)->not->toBeNull();

    // Segunda execução: idempotente — não deve fechar novamente
    $result2 = $action->execute($tenant);
    expect($result2['closed_ids'])->toBeEmpty();

    $ticket->refresh();
    // Verifica que closed_at não foi alterado (não houve re-fechamento)
    expect($ticket->closed_at->timestamp)->toBe($closedAt->timestamp);
});

// ── Cenário 8: Command processa múltiplos tenants ─────────────────

test('command processa multiplos tenants com tickets expirados', function (): void {
    $tenant1 = makeTenantWithAutoClose();
    $tenant2 = makeTenantWithAutoClose();
    $tenant3 = makeTenantWithAutoClose();

    $instance1 = makeActiveInstance($tenant1);
    $instance2 = makeActiveInstance($tenant2);
    $instance3 = makeActiveInstance($tenant3);

    $ticket1 = makeOpenTicket($tenant1, $instance1, ['last_message_at' => now()->subMinutes(60)]);
    $ticket2 = makeOpenTicket($tenant2, $instance2, ['last_message_at' => now()->subMinutes(60)]);
    $ticket3 = makeOpenTicket($tenant3, $instance3, ['last_message_at' => now()->subMinutes(60)]);

    $this->artisan('chat:close-inactive-tickets')
        ->assertSuccessful();

    $ticket1->refresh();
    $ticket2->refresh();
    $ticket3->refresh();

    expect($ticket1->status)->toBe('closed');
    expect($ticket2->status)->toBe('closed');
    expect($ticket3->status)->toBe('closed');
});

// ── Cenário 9: Command --tenant filtra corretamente ───────────────

test('command com opcao tenant processa apenas o tenant especificado', function (): void {
    $tenantX = makeTenantWithAutoClose();
    $tenantY = makeTenantWithAutoClose();

    $instanceX = makeActiveInstance($tenantX);
    $instanceY = makeActiveInstance($tenantY);

    $ticketX = makeOpenTicket($tenantX, $instanceX, ['last_message_at' => now()->subMinutes(60)]);
    $ticketY = makeOpenTicket($tenantY, $instanceY, ['last_message_at' => now()->subMinutes(60)]);

    $this->artisan('chat:close-inactive-tickets', [
        '--tenant' => $tenantX->id,
    ])->assertSuccessful();

    $ticketX->refresh();
    $ticketY->refresh();

    expect($ticketX->status)->toBe('closed');
    expect($ticketY->status)->toBe('open');
});

// ── Cenário 10: API salva config global ───────────────────────────

test('api salva e retorna configuracao global de auto close', function (): void {
    $tenant = PlatformTenant::factory()->create(['is_active' => true]);

    // Autenticar como super-admin
    $role = AuthRole::query()->firstOrCreate(
        ['id' => AuthRole::ADMINISTRADOR_ID],
        ['name' => AuthRole::ADMINISTRADOR_NAME, 'guard_name' => 'sanctum']
    );
    $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
    $user->assignRole($role);
    Sanctum::actingAs($user, abilities: ['*']);

    $payload = [
        'settings_chat' => [
            'auto_close_inactivity_enabled' => true,
            'auto_close_inactivity_minutes' => 30,
            'auto_close_inactivity_target' => 'both',
        ],
    ];

    // PATCH: atualiza settings
    $this->patchJson("/api/platform/tenants/{$tenant->id}/settings", $payload)
        ->assertOk()
        ->assertJsonPath('data.settings_chat.auto_close_inactivity_enabled', true)
        ->assertJsonPath('data.settings_chat.auto_close_inactivity_minutes', 30)
        ->assertJsonPath('data.settings_chat.auto_close_inactivity_target', 'both');

    // GET: verifica persistência
    $this->getJson("/api/platform/tenants/{$tenant->id}/settings")
        ->assertOk()
        ->assertJsonPath('data.settings_chat.auto_close_inactivity_enabled', true)
        ->assertJsonPath('data.settings_chat.auto_close_inactivity_minutes', 30);
});

// ── Cenário 11: API valida campos inválidos ───────────────────────

test('api rejeita auto_close_inactivity_minutes com valor invalido', function (): void {
    $tenant = PlatformTenant::factory()->create(['is_active' => true]);

    // Autenticar como super-admin
    $role = AuthRole::query()->firstOrCreate(
        ['id' => AuthRole::ADMINISTRADOR_ID],
        ['name' => AuthRole::ADMINISTRADOR_NAME, 'guard_name' => 'sanctum']
    );
    $user = AuthUser::factory()->create(['tenant_id' => $tenant->id]);
    $user->assignRole($role);
    Sanctum::actingAs($user, abilities: ['*']);

    $payload = [
        'settings_chat' => [
            'auto_close_inactivity_minutes' => 999,
        ],
    ];

    $this->patchJson("/api/platform/tenants/{$tenant->id}/settings", $payload)
        ->assertStatus(422);
});

// ── Cenário 12: TicketClosedEvent é disparado ─────────────────────

test('dispara ticket closed event com closed_mode auto_inactivity', function (): void {
    Event::fake([TicketClosedEvent::class]);

    $tenant = makeTenantWithAutoClose();
    $instance = makeActiveInstance($tenant);
    $ticket = makeOpenTicket($tenant, $instance, [
        'last_message_at' => now()->subMinutes(60),
    ]);

    $action = app(CloseInactiveTicketsAction::class);
    $result = $action->execute($tenant);

    expect($result['closed_ids'])->toContain((string) $ticket->id);

    Event::assertDispatched(TicketClosedEvent::class, function (TicketClosedEvent $event) use ($tenant, $ticket): bool {
        return $event->tenantId === (string) $tenant->id
            && $event->ticketId === (string) $ticket->id
            && $event->closedMode === 'auto_inactivity';
    });
});

// ── Cenário extra: Command com tenant inexistente retorna FAILURE ──

test('command com tenant inexistente retorna failure', function (): void {
    $this->artisan('chat:close-inactive-tickets', [
        '--tenant' => '00000000-0000-0000-0000-000000000000',
    ])->assertFailed();
});

// ── Cenário extra: Sem canais ativos retorna vazio ────────────────

test('retorna vazio quando tenant nao possui canais ativos', function (): void {
    $tenant = makeTenantWithAutoClose();

    // Nenhuma instância criada

    $action = app(CloseInactiveTicketsAction::class);
    $result = $action->execute($tenant);

    expect($result['closed_ids'])->toBeEmpty();
    expect($result['skipped_ids'])->toBeEmpty();
});

// ── Cenário extra: Não fecha tickets que já tem closed_mode preenchido ──

test('nao considera tickets que ja possuem closed_mode preenchido', function (): void {
    $tenant = makeTenantWithAutoClose();
    $instance = makeActiveInstance($tenant);

    // Ticket já fechado manualmente (closed_mode='normal')
    $ticket = ChatTicket::factory()->create([
        'tenant_id' => $tenant->id,
        'instance_id' => $instance->id,
        'status' => 'closed',
        'closed_mode' => 'normal',
        'closed_at' => now()->subHour(),
        'last_message_at' => now()->subMinutes(60),
    ]);

    $action = app(CloseInactiveTicketsAction::class);
    $result = $action->execute($tenant);

    expect($result['closed_ids'])->toBeEmpty();
    expect($result['skipped_ids'])->toBeEmpty();
});

// ── Cenário extra: Config herdada do tenant quando canal não tem override ──

test('herda config do tenant quando canal nao possui override', function (): void {
    $tenant = makeTenantWithAutoClose([
        'settings_chat' => [
            'auto_close_inactivity_enabled' => true,
            'auto_close_inactivity_minutes' => 45,
            'auto_close_inactivity_target' => 'agent',
        ],
    ]);

    // Instância sem override de auto-close (todos null)
    $instance = makeActiveInstance($tenant, [
        'auto_close_enabled' => null,
        'auto_close_after_minutes' => null,
        'auto_close_target' => null,
    ]);

    // Ticket com agente inativo há 60 min (target=agent herdado do tenant, 45 min)
    $ticket = makeOpenTicket($tenant, $instance, [
        'last_agent_message_at' => now()->subMinutes(60),
        'last_customer_message_at' => now()->subMinutes(5),
        'last_message_at' => now()->subMinutes(60),
    ]);

    $action = app(CloseInactiveTicketsAction::class);
    $result = $action->execute($tenant);

    expect($result['closed_ids'])->toContain((string) $ticket->id);

    $ticket->refresh();
    expect($ticket->status)->toBe('closed');
    expect($ticket->closed_mode)->toBe('auto_inactivity');
});
