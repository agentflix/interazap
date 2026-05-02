<?php

declare(strict_types=1);

use Domain\Chat\Actions\CloseInactiveTicketsAction;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

// ── Helpers ───────────────────────────────────────────────────────

/**
 * Cria um tenant com configurações de auto-close.
 *
 * @param  array<string, mixed>  $overrides
 */
function unitMakeTenantWithAutoClose(array $overrides = []): PlatformTenant
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
function unitMakeActiveInstance(PlatformTenant $tenant, array $overrides = []): ChatInstance
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
function unitMakeOpenTicket(PlatformTenant $tenant, ChatInstance $instance, array $overrides = []): ChatTicket
{
    return ChatTicket::factory()->create(array_merge([
        'tenant_id' => $tenant->id,
        'instance_id' => $instance->id,
        'status' => 'open',
        'last_message_at' => now()->subMinutes(60),
    ], $overrides));
}

// ── Cenário 1: Config global desabilitada, canal override habilitado ─

test('fecha tickets quando global esta desabilitado mas canal tem override habilitado', function (): void {
    $tenant = unitMakeTenantWithAutoClose([
        'settings_chat' => [
            'auto_close_inactivity_enabled' => false,
            'auto_close_inactivity_minutes' => 30,
            'auto_close_inactivity_target' => 'both',
        ],
    ]);

    $instance = unitMakeActiveInstance($tenant, [
        'auto_close_enabled' => true,
        'auto_close_after_minutes' => 15,
    ]);

    $ticket = unitMakeOpenTicket($tenant, $instance, [
        'last_message_at' => now()->subMinutes(20),
    ]);

    $action = app(CloseInactiveTicketsAction::class);
    $result = $action->execute($tenant);

    expect($result['closed_ids'])->toContain((string) $ticket->id);

    $ticket->refresh();
    expect($ticket->status)->toBe('closed');
    expect($ticket->closed_mode)->toBe('auto_inactivity');
});

// ── Cenário 2: Config global desabilitada, canal sem override ───────

test('nao fecha tickets quando global desabilitado e canal sem override', function (): void {
    $tenant = unitMakeTenantWithAutoClose([
        'settings_chat' => [
            'auto_close_inactivity_enabled' => false,
            'auto_close_inactivity_minutes' => 30,
            'auto_close_inactivity_target' => 'both',
        ],
    ]);

    $instance = unitMakeActiveInstance($tenant, [
        'auto_close_enabled' => null,
    ]);

    $ticket = unitMakeOpenTicket($tenant, $instance, [
        'last_message_at' => now()->subMinutes(60),
    ]);

    $action = app(CloseInactiveTicketsAction::class);
    $result = $action->execute($tenant);

    expect($result['closed_ids'])->toBeEmpty();

    $ticket->refresh();
    expect($ticket->status)->toBe('open');
});

// ── Cenário 3: Idempotência ────────────────────────────────────────

test('execucao dupla nao fecha novamente tickets ja fechados', function (): void {
    $tenant = unitMakeTenantWithAutoClose();
    $instance = unitMakeActiveInstance($tenant);
    $ticket = unitMakeOpenTicket($tenant, $instance, [
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
    expect($ticket->closed_at->timestamp)->toBe($closedAt->timestamp);
});

// ── Cenário 4: Target 'client' usa last_customer_message_at ───────

test('target client usa last_customer_message_at para definir inatividade', function (): void {
    $tenant = unitMakeTenantWithAutoClose();

    $instance = unitMakeActiveInstance($tenant, [
        'auto_close_target' => 'client',
        'auto_close_after_minutes' => 15,
    ]);

    $ticket = unitMakeOpenTicket($tenant, $instance, [
        'last_message_at' => now()->subMinutes(60),
        'last_customer_message_at' => now()->subMinutes(60),
        'last_agent_message_at' => now()->subMinutes(5),
    ]);

    $action = app(CloseInactiveTicketsAction::class);
    $result = $action->execute($tenant);

    expect($result['closed_ids'])->toContain((string) $ticket->id);

    $ticket->refresh();
    expect($ticket->status)->toBe('closed');
});

// ── Cenário 5: Target 'agent' usa last_agent_message_at ───────────

test('target agent usa last_agent_message_at para definir inatividade', function (): void {
    $tenant = unitMakeTenantWithAutoClose();

    $instance = unitMakeActiveInstance($tenant, [
        'auto_close_target' => 'agent',
        'auto_close_after_minutes' => 15,
    ]);

    $ticket = unitMakeOpenTicket($tenant, $instance, [
        'last_message_at' => now()->subMinutes(60),
        'last_agent_message_at' => now()->subMinutes(60),
        'last_customer_message_at' => now()->subMinutes(5),
    ]);

    $action = app(CloseInactiveTicketsAction::class);
    $result = $action->execute($tenant);

    expect($result['closed_ids'])->toContain((string) $ticket->id);

    $ticket->refresh();
    expect($ticket->status)->toBe('closed');
});

// ── Cenário 6: Target 'both' usa last_message_at ──────────────────

test('target both usa last_message_at para definir inatividade', function (): void {
    $tenant = unitMakeTenantWithAutoClose();

    $instance = unitMakeActiveInstance($tenant, [
        'auto_close_target' => 'both',
        'auto_close_after_minutes' => 15,
    ]);

    $ticket = unitMakeOpenTicket($tenant, $instance, [
        'last_message_at' => now()->subMinutes(60),
    ]);

    $action = app(CloseInactiveTicketsAction::class);
    $result = $action->execute($tenant);

    expect($result['closed_ids'])->toContain((string) $ticket->id);

    $ticket->refresh();
    expect($ticket->status)->toBe('closed');
});
