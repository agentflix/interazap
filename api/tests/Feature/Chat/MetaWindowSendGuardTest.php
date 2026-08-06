<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use Domain\Chat\Actions\SendChatMessageAction;
use Domain\Chat\DTOs\ChatMessageDTO;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Date;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Testes do guard de janela Meta para todo texto livre outbound
 * (agente, BOT e IA) — template aprovado é a única exceção.
 *
 * Cobre:
 * - agente/BOT/IA fora da janela → bloqueados (ValidationException)
 * - dentro da janela → permitidos
 * - ticket ausente → fail-closed (bloqueado)
 * - template aprovado → permitido mesmo fora da janela
 */
class MetaWindowSendGuardTest extends TestCase
{
    use LazilyRefreshDatabase;

    private SendChatMessageAction $action;

    private PlatformTenant $tenant;

    private CRMContact $contact;

    private ChatInstance $instance;

    private ChatTicket $ticket;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = $this->app->make(SendChatMessageAction::class);
        $this->tenant = PlatformTenant::factory()->create();
        $this->contact = CRMContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->instance = ChatInstance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'meta',
            'webhook_token' => 'meta-guard-token',
            'settings_json' => [
                'phone_number_id' => '9876543210',
                'access_token' => 'secret',
            ],
        ]);
        $this->ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'instance_id' => $this->instance->id,
            'status' => 'open',
        ]);
    }

    private function dto(string $source, string $type = 'text'): ChatMessageDTO
    {
        return new ChatMessageDTO(
            ticketId: $this->ticket->id,
            content: 'Mensagem de teste',
            direction: 'outgoing',
            type: $type,
            isFromContact: false,
            source: $source,
        );
    }

    /** Força janela FECHADA (sem mensagem recente, sem janela persistida). */
    private function closeWindow(): void
    {
        ChatMessage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ticket_id' => $this->ticket->id,
            'contact_id' => $this->contact->id,
            'is_from_contact' => true,
            'created_at' => Date::now()->subHours(25),
        ]);
    }

    /** Força janela ABERTA (inbound recente). */
    private function openWindow(): void
    {
        ChatMessage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ticket_id' => $this->ticket->id,
            'contact_id' => $this->contact->id,
            'is_from_contact' => true,
            'created_at' => Date::now()->subHours(1),
        ]);
    }

    public function test_agent_text_outside_window_is_blocked(): void
    {
        $this->closeWindow();

        $this->expectException(ValidationException::class);

        $this->action->create((string) $this->tenant->id, $this->dto(ChatMessageDTO::SOURCE_AGENT));
    }

    public function test_bot_text_outside_window_is_blocked(): void
    {
        $this->closeWindow();

        $this->expectException(ValidationException::class);

        $this->action->create((string) $this->tenant->id, $this->dto(ChatMessageDTO::SOURCE_BOT));
    }

    public function test_ai_text_outside_window_is_blocked(): void
    {
        $this->closeWindow();

        $this->expectException(ValidationException::class);

        $this->action->create((string) $this->tenant->id, $this->dto(ChatMessageDTO::SOURCE_AI));
    }

    public function test_agent_bot_ai_text_inside_window_are_allowed(): void
    {
        $this->openWindow();

        foreach ([
            ChatMessageDTO::SOURCE_AGENT,
            ChatMessageDTO::SOURCE_BOT,
            ChatMessageDTO::SOURCE_AI,
        ] as $source) {
            $message = $this->action->create((string) $this->tenant->id, $this->dto($source));
            $this->assertNotNull($message->id);
        }
    }

    public function test_missing_ticket_fails_closed_for_agent(): void
    {
        $this->closeWindow();

        $this->expectException(ValidationException::class);

        $this->action->create((string) $this->tenant->id, new ChatMessageDTO(
            ticketId: '00000000-0000-0000-0000-000000000000',
            content: 'Sem ticket',
            direction: 'outgoing',
            type: 'text',
            isFromContact: false,
            source: ChatMessageDTO::SOURCE_AGENT,
        ));
    }

    public function test_missing_ticket_fails_closed_for_ai(): void
    {
        $this->closeWindow();

        $this->expectException(ValidationException::class);

        $this->action->create((string) $this->tenant->id, new ChatMessageDTO(
            ticketId: '00000000-0000-0000-0000-000000000000',
            content: 'Sem ticket',
            direction: 'outgoing',
            type: 'text',
            isFromContact: false,
            source: ChatMessageDTO::SOURCE_AI,
        ));
    }

    public function test_template_outside_window_is_allowed(): void
    {
        $this->closeWindow();

        $message = $this->action->create(
            (string) $this->tenant->id,
            $this->dto(ChatMessageDTO::SOURCE_AGENT, 'template'),
        );

        $this->assertNotNull($message->id);
    }

    public function test_non_meta_instance_is_not_blocked(): void
    {
        $this->closeWindow();

        $uazapiInstance = ChatInstance::factory()->create([
            'tenant_id' => $this->tenant->id,
            'provider' => 'uazapi',
            'webhook_token' => 'uazapi-guard-token',
        ]);
        $uazapiTicket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
            'instance_id' => $uazapiInstance->id,
            'status' => 'open',
        ]);

        $message = $this->action->create((string) $this->tenant->id, new ChatMessageDTO(
            ticketId: $uazapiTicket->id,
            content: 'Sem janela Meta',
            direction: 'outgoing',
            type: 'text',
            isFromContact: false,
            source: ChatMessageDTO::SOURCE_AGENT,
        ));

        $this->assertNotNull($message->id);
    }
}
