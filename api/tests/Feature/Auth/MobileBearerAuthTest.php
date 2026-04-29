<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * TASK-047.26 — Autorização de canal Broadcast com Bearer token mobile.
 *
 * Valida que o endpoint /api/broadcasting/auth:
 * - Autoriza o tenant correto com token Sanctum válido
 * - Bloqueia acesso a canal de outro tenant (isolamento)
 * - Rejeita tokens inválidos (401)
 * - Rejeita tokens revogados (401)
 */
class MobileBearerAuthTest extends TestCase
{
    use LazilyRefreshDatabase;

    // -------------------------------------------------------------------------
    // Tenant channel
    // -------------------------------------------------------------------------

    public function test_autoriza_canal_tenant_com_bearer_do_proprio_tenant(): void
    {
        $user = AuthUser::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-tenant.{$user->tenant_id}",
        ])->assertOk();
    }

    public function test_bloqueia_canal_tenant_de_outro_tenant(): void
    {
        $user = AuthUser::factory()->create();
        $outherTenantId = Str::uuid()->toString();

        Sanctum::actingAs($user);

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-tenant.{$outherTenantId}",
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Ticket channel
    // -------------------------------------------------------------------------

    public function test_autoriza_canal_ticket_do_proprio_tenant(): void
    {
        $user = AuthUser::factory()->create();
        $ticket = ChatTicket::factory()->create(['tenant_id' => $user->tenant_id]);

        Sanctum::actingAs($user);

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-ticket.{$ticket->id}",
        ])->assertOk();
    }

    public function test_bloqueia_canal_ticket_de_outro_tenant(): void
    {
        $user = AuthUser::factory()->create();
        // Ticket pertence a outro tenant
        $ticket = ChatTicket::factory()->create();

        Sanctum::actingAs($user);

        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-ticket.{$ticket->id}",
        ])->assertForbidden();
    }

    // -------------------------------------------------------------------------
    // Autenticação inválida / revogada
    // -------------------------------------------------------------------------

    public function test_rejeita_request_sem_bearer(): void
    {
        $user = AuthUser::factory()->create();

        // Sem autenticação
        $this->postJson('/api/broadcasting/auth', [
            'socket_id' => '123.456',
            'channel_name' => "private-tenant.{$user->tenant_id}",
        ])->assertUnauthorized();
    }

    public function test_rejeita_token_invalido(): void
    {
        $user = AuthUser::factory()->create();

        $this->withToken('token-invalido-xyzzy')
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => "private-tenant.{$user->tenant_id}",
            ])->assertUnauthorized();
    }

    public function test_rejeita_token_revogado(): void
    {
        $user = AuthUser::factory()->create();
        $token = $user->createToken('mobile')->plainTextToken;

        // Revoga todos os tokens
        $user->tokens()->delete();

        $this->withToken($token)
            ->postJson('/api/broadcasting/auth', [
                'socket_id' => '123.456',
                'channel_name' => "private-tenant.{$user->tenant_id}",
            ])->assertUnauthorized();
    }
}
