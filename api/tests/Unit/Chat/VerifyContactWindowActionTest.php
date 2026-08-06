<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Chat\Actions\VerifyContactWindowAction;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

/**
 * Testes unitários para VerifyContactWindowAction.
 *
 * Casos de borda da janela 24h:
 * - 23h59m → DENTRO (canSendFreeText = true)
 * - 24h00m → FORA (canSendFreeText = false)
 * - 24h01m → FORA (canSendFreeText = false)
 * - Sem mensagens → FORA (canSendFreeText = false)
 * - Contexto ausente → FORA (fail-closed)
 * - system nunca abre fallback
 * - ticket de outra instância/canal não autoriza
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

    private function makeContext(
        string $tenantId,
        string $contactId,
        ?string $channel = 'meta',
    ): ChatTicket {
        $instance = ChatInstance::factory()->create([
            'tenant_id' => $tenantId,
            'provider' => 'meta',
            'webhook_token' => 'whk-'.uniqid(),
            'settings_json' => ['phone_number_id' => '9999'.uniqid()],
        ]);

        return ChatTicket::factory()->create([
            'tenant_id' => $tenantId,
            'contact_id' => $contactId,
            'instance_id' => $instance->id,
            'channel' => $channel,
            'status' => 'open',
        ]);
    }

    public function test_contact_with_message_23h59m_ago_can_send_free_text(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = $this->makeContext((string) $tenant->id, (string) $contact->id);

        // Mensagem há 23h59m (1 minuto antes do cutoff)
        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'is_from_contact' => true,
            'created_at' => Date::now()->subHours(23)->subMinutes(59),
        ]);

        $result = $this->action->execute((string) $tenant->id, (string) $contact->id, (string) $ticket->id, (string) $ticket->instance_id);

        $this->assertTrue($result->canSendFreeText);
        $this->assertNotNull($result->lastMessageAt);
    }

    public function test_contact_with_message_24h00m_ago_cannot_send_free_text(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = $this->makeContext((string) $tenant->id, (string) $contact->id);

        // Mensagem há exatamente 24h (no cutoff, deve ser FORA)
        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'is_from_contact' => true,
            'created_at' => Date::now()->subHours(24),
        ]);

        $result = $this->action->execute((string) $tenant->id, (string) $contact->id, (string) $ticket->id, (string) $ticket->instance_id);

        $this->assertFalse($result->canSendFreeText);
        $this->assertNotNull($result->lastMessageAt);
    }

    public function test_contact_with_message_24h01m_ago_cannot_send_free_text(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = $this->makeContext((string) $tenant->id, (string) $contact->id);

        // Mensagem há 24h01m (apos cutoff)
        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'is_from_contact' => true,
            'created_at' => Date::now()->subHours(24)->subMinutes(1),
        ]);

        $result = $this->action->execute((string) $tenant->id, (string) $contact->id, (string) $ticket->id, (string) $ticket->instance_id);

        $this->assertFalse($result->canSendFreeText);
        $this->assertNotNull($result->lastMessageAt);
    }

    public function test_contact_without_messages_cannot_send_free_text(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = $this->makeContext((string) $tenant->id, (string) $contact->id);

        $result = $this->action->execute((string) $tenant->id, (string) $contact->id, (string) $ticket->id, (string) $ticket->instance_id);

        $this->assertFalse($result->canSendFreeText);
        $this->assertNull($result->lastMessageAt);
    }

    public function test_missing_context_fails_closed(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);

        $result = $this->action->execute((string) $tenant->id, (string) $contact->id);

        $this->assertFalse($result->canSendFreeText);
        $this->assertNull($result->lastMessageAt);
    }

    public function test_ticket_from_other_instance_does_not_authorize(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);

        // Ticket do contexto (instância A) com mensagem recente.
        $ticketA = $this->makeContext((string) $tenant->id, (string) $contact->id);
        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticketA->id,
            'contact_id' => $contact->id,
            'is_from_contact' => true,
            'created_at' => Date::now()->subHours(1),
        ]);

        // Ticket de OUTRA instância (canal telegram — não autoriza Meta).
        $ticketB = $this->makeContext((string) $tenant->id, (string) $contact->id, 'telegram');
        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticketB->id,
            'contact_id' => $contact->id,
            'is_from_contact' => true,
            'created_at' => Date::now()->subMinutes(30),
        ]);

        // Pedindo a janela da instância B (telegram) com o ticket A → contexto
        // divergente: ticket A não pertence ao instance B → não autoriza.
        $result = $this->action->execute(
            (string) $tenant->id,
            (string) $contact->id,
            (string) $ticketA->id,
            (string) $ticketB->instance_id,
        );

        $this->assertFalse($result->canSendFreeText);

        // Pedindo o ticket A com a instância A → autoriza.
        $resultOk = $this->action->execute(
            (string) $tenant->id,
            (string) $contact->id,
            (string) $ticketA->id,
            (string) $ticketA->instance_id,
        );
        $this->assertTrue($resultOk->canSendFreeText);
    }

    public function test_system_message_never_opens_fallback(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = $this->makeContext((string) $tenant->id, (string) $contact->id);

        // Única mensagem do contato é type=system — nunca abre fallback.
        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'type' => 'system',
            'is_from_contact' => true,
            'created_at' => Date::now()->subMinutes(5),
        ]);

        $result = $this->action->execute((string) $tenant->id, (string) $contact->id, (string) $ticket->id, (string) $ticket->instance_id);

        $this->assertFalse($result->canSendFreeText);
        $this->assertNull($result->lastMessageAt);
    }

    public function test_only_contact_messages_count_not_agent_messages(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = $this->makeContext((string) $tenant->id, (string) $contact->id);

        // Mensagem do agente (is_from_contact = false) - não deve contar
        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'is_from_contact' => false,
            'created_at' => Date::now()->subMinutes(30),
        ]);

        // Mensagem do contato (is_from_contact = true) - deve contar
        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'is_from_contact' => true,
            'created_at' => Date::now()->subHours(1),
        ]);

        $result = $this->action->execute((string) $tenant->id, (string) $contact->id, (string) $ticket->id, (string) $ticket->instance_id);

        $this->assertTrue($result->canSendFreeText);
        $this->assertNotNull($result->lastMessageAt);
    }

    public function test_uses_most_recent_contact_message(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = $this->makeContext((string) $tenant->id, (string) $contact->id);

        // Mensagem antiga há 25h (seria fora da janela sozinha)
        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'is_from_contact' => true,
            'created_at' => Date::now()->subHours(25),
        ]);

        // Mensagem recente há 1h (dentro da janela)
        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'is_from_contact' => true,
            'created_at' => Date::now()->subHours(1),
        ]);

        $result = $this->action->execute((string) $tenant->id, (string) $contact->id, (string) $ticket->id, (string) $ticket->instance_id);

        // Deve usar a mensagem mais recente (1h atrás), que está dentro da janela
        $this->assertTrue($result->canSendFreeText);
    }

    public function test_persisted_meta_window_in_the_future_is_authoritative(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = $this->makeContext((string) $tenant->id, (string) $contact->id);
        $expiresAt = Date::now()->addHours(50)->startOfSecond();

        $ticket->update([
            'meta_window_expires_at' => $expiresAt,
            'meta_window_type' => '72h',
        ]);

        // Sem nenhuma mensagem no banco — o campo persistido deve bastar (Branch 1).
        $result = $this->action->execute((string) $tenant->id, (string) $contact->id, (string) $ticket->id, (string) $ticket->instance_id);

        $this->assertTrue($result->canSendFreeText);
        $this->assertSame('72h', $result->windowType);
        $this->assertNotNull($result->expiresAt);
        $this->assertTrue($result->expiresAt->equalTo($expiresAt));
    }

    public function test_persisted_meta_window_in_the_past_falls_back_to_message_calculation(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $contact = CRMContact::factory()->create(['tenant_id' => $tenant->id]);
        $ticket = $this->makeContext((string) $tenant->id, (string) $contact->id);

        $ticket->update([
            'meta_window_expires_at' => Date::now()->subHours(3),
            'meta_window_type' => '24h',
        ]);

        // Inbound recente (1h atrás) — a janela persistida está no passado,
        // então o fallback por mensagens deve prevalecer (Branch 2).
        ChatMessage::factory()->create([
            'tenant_id' => $tenant->id,
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'is_from_contact' => true,
            'created_at' => Date::now()->subHours(1),
        ]);

        $result = $this->action->execute((string) $tenant->id, (string) $contact->id, (string) $ticket->id, (string) $ticket->instance_id);

        $this->assertTrue($result->canSendFreeText);
        $this->assertSame('24h', $result->windowType);
    }
}
