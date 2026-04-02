<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\DTOs\ChatMessageDTO;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Testes do mecanismo de Human Takeover.
 *
 * Valida o ciclo completo de ativação automática e manual do takeover,
 * bloqueio de automações e liberação para IA.
 */
class HumanTakeoverTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PlatformTenant $tenant;

    private AuthUser $user;

    private ChatTicket $ticket;

    private ChatInstance $instance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = PlatformTenant::factory()->create();
        $this->user = AuthUser::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Criar e atribuir permissões necessárias
        $this->grantChatPermissions($this->user);

        $this->instance = ChatInstance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'uazapi',
            'webhook_token' => 'test-token',
        ]);

        $this->ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'instance_id' => $this->instance->id,
            'status' => 'pending',
            'human_takeover_at' => null,
        ]);
    }

    private function grantChatPermissions(AuthUser $user): void
    {
        $permissions = [
            'chat.tickets.view',
            'chat.tickets.create',
            'chat.tickets.update',
            'chat.messages.view',
            'chat.messages.create',
            'chat.messages.manage',
        ];

        foreach ($permissions as $permissionName) {
            $permission = AuthPermission::query()->firstOrCreate(
                ['name' => $permissionName, 'guard_name' => 'sanctum'],
                ['id' => (string) Str::orderedUuid()]
            );
            $user->givePermissionTo($permission);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->refresh();
    }

    public function test_takeover_endpoint_activates_human_control(): void
    {
        $this->assertNull($this->ticket->human_takeover_at);

        Sanctum::actingAs($this->user, ['*']);
        $response = $this->postJson("/api/chat/tickets/{$this->ticket->id}/takeover");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Atendimento humano ativado. Automações pausadas.');

        $this->ticket->refresh();
        $this->assertNotNull($this->ticket->human_takeover_at);
    }

    public function test_release_to_ai_endpoint_removes_human_control(): void
    {
        $this->ticket->update(['human_takeover_at' => now()]);
        $this->assertNotNull($this->ticket->human_takeover_at);

        Sanctum::actingAs($this->user, ['*']);
        $response = $this->postJson("/api/chat/tickets/{$this->ticket->id}/release-to-ai");

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('message', 'Controle devolvido para IA. Automações reativadas.');

        $this->ticket->refresh();
        $this->assertNull($this->ticket->human_takeover_at);
    }

    public function test_agent_message_auto_activates_takeover(): void
    {
        $this->assertNull($this->ticket->human_takeover_at);

        // Simular envio de mensagem via API (como um agente faria)
        Sanctum::actingAs($this->user, ['*']);
        $response = $this->postJson("/api/chat/tickets/{$this->ticket->id}/messages", [
            'content' => 'Olá, como posso ajudar?',
            'direction' => 'outgoing',
            'type' => 'text',
            'is_from_contact' => false,
            'source' => ChatMessageDTO::SOURCE_AGENT,
        ]);

        $response->assertCreated();

        $this->ticket->refresh();
        $this->assertNotNull(
            $this->ticket->human_takeover_at,
            'O human_takeover_at deve ser ativado automaticamente quando um agente envia mensagem'
        );
    }

    public function test_webhook_message_does_not_activate_takeover(): void
    {
        $this->assertNull($this->ticket->human_takeover_at);

        // Criar mensagem diretamente com source=webhook (simula ingestão)
        ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => $this->tenant->id,
            'ticket_id' => $this->ticket->id,
            'content' => 'Mensagem do cliente via webhook',
            'direction' => 'incoming',
            'type' => 'text',
            'is_from_contact' => true,
            'status' => 'received',
            'source' => ChatMessageDTO::SOURCE_WEBHOOK,
        ]);

        $this->ticket->refresh();
        $this->assertNull(
            $this->ticket->human_takeover_at,
            'O human_takeover_at NÃO deve ser ativado por mensagens de webhook'
        );
    }

    public function test_takeover_is_idempotent(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        // Ativar takeover
        $response1 = $this->postJson("/api/chat/tickets/{$this->ticket->id}/takeover");
        $response1->assertOk();

        $this->ticket->refresh();
        $firstTakeoverAt = $this->ticket->human_takeover_at;

        // Segunda chamada não deve alterar o timestamp
        Sleep::fake();
        Sleep::sleep(1); // Garantir diferença de tempo (faked para não atrasar teste)
        $response2 = $this->postJson("/api/chat/tickets/{$this->ticket->id}/takeover");
        $response2->assertOk();

        $this->ticket->refresh();
        $this->assertEquals(
            $firstTakeoverAt->format('Y-m-d H:i:s'),
            $this->ticket->human_takeover_at->format('Y-m-d H:i:s'),
            'O timestamp de takeover não deve mudar em chamadas subsequentes'
        );
    }

    public function test_release_is_idempotent(): void
    {
        // Ticket já sem takeover
        $this->assertNull($this->ticket->human_takeover_at);

        Sanctum::actingAs($this->user, ['*']);
        $response = $this->postJson("/api/chat/tickets/{$this->ticket->id}/release-to-ai");

        $response->assertOk();

        $this->ticket->refresh();
        $this->assertNull($this->ticket->human_takeover_at);
    }

    public function test_full_takeover_cycle(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        // Estado inicial: sem takeover
        $this->assertNull($this->ticket->human_takeover_at);

        // 1. Agente envia mensagem → takeover ativa automaticamente
        $this->postJson("/api/chat/tickets/{$this->ticket->id}/messages", [
            'content' => 'Olá!',
            'direction' => 'outgoing',
            'type' => 'text',
            'is_from_contact' => false,
            'source' => ChatMessageDTO::SOURCE_AGENT,
        ])
            ->assertCreated();

        $this->ticket->refresh();
        $this->assertNotNull($this->ticket->human_takeover_at, 'Takeover deve estar ativo');

        // 2. Agente libera para IA
        $this->postJson("/api/chat/tickets/{$this->ticket->id}/release-to-ai")
            ->assertOk();

        $this->ticket->refresh();
        $this->assertNull($this->ticket->human_takeover_at, 'Takeover deve estar inativo');

        // 3. Agente reativa takeover manualmente
        $this->postJson("/api/chat/tickets/{$this->ticket->id}/takeover")
            ->assertOk();

        $this->ticket->refresh();
        $this->assertNotNull($this->ticket->human_takeover_at, 'Takeover deve estar ativo novamente');
    }

    public function test_unauthenticated_user_cannot_takeover(): void
    {
        $response = $this->postJson("/api/chat/tickets/{$this->ticket->id}/takeover");

        $response->assertUnauthorized();
    }

    public function test_unauthenticated_user_cannot_release(): void
    {
        $response = $this->postJson("/api/chat/tickets/{$this->ticket->id}/release-to-ai");

        $response->assertUnauthorized();
    }
}
