<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Carbon\Carbon;
use Domain\Chat\Actions\VerifyContactWindowAction;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Testes unitários para VerifyContactWindowAction.
 *
 * Casos de borda da janela 24h:
 * - 23h59m → DENTRO (canSendFreeText = true)
 * - 24h00m → FORA (canSendFreeText = false)
 * - 24h01m → FORA (canSendFreeText = false)
 * - Sem mensagens → FORA (canSendFreeText = false)
 */
class VerifyContactWindowActionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private VerifyContactWindowAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new VerifyContactWindowAction;
    }

    public function test_contact_with_message_23h59m_ago_can_send_free_text(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = ChatTicket::factory()->create(['tenant_id' => $tenant->id]);

        // Mensagem há 23h59m (1 minuto antes do cutoff)
        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'is_from_contact' => true,
            'created_at' => Carbon::now()->subHours(23)->subMinutes(59),
        ]);

        $result = $this->action->execute($tenant->id, $contact->id);

        $this->assertTrue($result->canSendFreeText);
        $this->assertNotNull($result->lastMessageAt);
    }

    public function test_contact_with_message_24h00m_ago_cannot_send_free_text(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = ChatTicket::factory()->create(['tenant_id' => $tenant->id]);

        // Mensagem há exatamente 24h (no cutoff, deve ser FORA)
        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'is_from_contact' => true,
            'created_at' => Carbon::now()->subHours(24),
        ]);

        $result = $this->action->execute($tenant->id, $contact->id);

        $this->assertFalse($result->canSendFreeText);
        $this->assertNotNull($result->lastMessageAt);
    }

    public function test_contact_with_message_24h01m_ago_cannot_send_free_text(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = ChatTicket::factory()->create(['tenant_id' => $tenant->id]);

        // Mensagem há 24h01m (apos cutoff)
        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'is_from_contact' => true,
            'created_at' => Carbon::now()->subHours(24)->subMinutes(1),
        ]);

        $result = $this->action->execute($tenant->id, $contact->id);

        $this->assertFalse($result->canSendFreeText);
        $this->assertNotNull($result->lastMessageAt);
    }

    public function test_contact_without_messages_cannot_send_free_text(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);

        $result = $this->action->execute($tenant->id, $contact->id);

        $this->assertFalse($result->canSendFreeText);
        $this->assertNull($result->lastMessageAt);
    }

    public function test_contact_from_different_tenant_is_not_found(): void
    {
        $tenant1 = PlatformTenant::factory()->create();
        $tenant2 = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant1->id]);
        $ticket = ChatTicket::factory()->create(['tenant_id' => $tenant1->id]);

        // Mensagem pertence ao tenant1
        ChatMessage::factory()->create([
            'tenant_id' => $tenant1->id,
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'is_from_contact' => true,
            'created_at' => Carbon::now()->subHours(1),
        ]);

        // Buscando como tenant2 deve retornar sem mensagens
        $result = $this->action->execute($tenant2->id, $contact->id);

        $this->assertFalse($result->canSendFreeText);
        $this->assertNull($result->lastMessageAt);
    }

    public function test_only_contact_messages_count_not_agent_messages(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = ChatTicket::factory()->create(['tenant_id' => $tenant->id]);

        // Mensagem do agente (is_from_contact = false) - não deve contar
        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'is_from_contact' => false,
            'created_at' => Carbon::now()->subMinutes(30),
        ]);

        // Mensagem do contato (is_from_contact = true) - deve contar
        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'is_from_contact' => true,
            'created_at' => Carbon::now()->subHours(1),
        ]);

        $result = $this->action->execute($tenant->id, $contact->id);

        $this->assertTrue($result->canSendFreeText);
        $this->assertNotNull($result->lastMessageAt);
    }

    public function test_uses_most_recent_contact_message(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = ChatTicket::factory()->create(['tenant_id' => $tenant->id]);

        // Mensagem antiga há 25h (seria fora da janela sozinha)
        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'is_from_contact' => true,
            'created_at' => Carbon::now()->subHours(25),
        ]);

        // Mensagem recente há 1h (dentro da janela)
        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'is_from_contact' => true,
            'created_at' => Carbon::now()->subHours(1),
        ]);

        $result = $this->action->execute($tenant->id, $contact->id);

        // Deve usar a mensagem mais recente (1h atrás), que está dentro da janela
        $this->assertTrue($result->canSendFreeText);
    }
}
