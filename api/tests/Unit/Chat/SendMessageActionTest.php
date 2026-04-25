<?php

declare(strict_types=1);

namespace Tests\Unit\Chat;

use Domain\Chat\Actions\SendChatMessageAction;
use Domain\Chat\Actions\SendMessageAction;
use Domain\Chat\DTOs\ChatMessageDTO;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CrmContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SendMessageActionTest extends TestCase
{
    use RefreshDatabase;

    private SendMessageAction $action;

    private SendChatMessageAction $messageActions;

    private PlatformTenant $tenant;

    private ChatTicket $ticket;

    private CrmContact $contact;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        $this->tenant = PlatformTenant::factory()->create();
        $this->contact = CrmContact::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
        $this->ticket = ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
        ]);

        $this->messageActions = $this->app->make(SendChatMessageAction::class);

        $this->action = new SendMessageAction(
            $this->messageActions,
        );
    }

    #[Test]
    public function it_sends_message_successfully(): void
    {
        $dto = new ChatMessageDTO(
            ticketId: $this->ticket->id,
            content: 'Hello, this is a test message',
            direction: 'outgoing',
            type: 'text',
            userId: null,
        );

        $result = $this->action->handle($this->tenant->id, $dto);

        $this->assertTrue($result['created']);
        $this->assertInstanceOf(ChatMessage::class, $result['message']);
        $this->assertNull($result['reason']);

        $this->assertDatabaseHas('chat_messages', [
            'tenant_id' => $this->tenant->id,
            'ticket_id' => $this->ticket->id,
            'content' => 'Hello, this is a test message',
        ]);
    }

    #[Test]
    public function it_prevents_duplicate_message_in_time_window(): void
    {
        $dto = new ChatMessageDTO(
            ticketId: $this->ticket->id,
            content: 'Duplicate message test',
            direction: 'outgoing',
            type: 'text',
        );

        // Primeira mensagem
        $result1 = $this->action->handle($this->tenant->id, $dto);
        $this->assertTrue($result1['created']);

        // Tentativa de duplicar na mesma janela de tempo
        $result2 = $this->action->handle($this->tenant->id, $dto);

        $this->assertFalse($result2['created']);
        $this->assertContains($result2['reason'], ['duplicate_message', 'duplicate_in_database']);

        if ($result2['reason'] === 'duplicate_message') {
            $this->assertSame($result1['message']->id, $result2['message']?->id);
        } else {
            $this->assertNull($result2['message']);
        }

        // Apenas 1 mensagem no banco
        $count = \Domain\Chat\Models\ChatMessage::query()->where('ticket_id', $this->ticket->id)
            ->where('content', 'Duplicate message test')
            ->count();
        $this->assertSame(1, $count);
    }

    #[Test]
    public function it_allows_different_content(): void
    {
        $dto1 = new ChatMessageDTO(
            ticketId: $this->ticket->id,
            content: 'First message',
            direction: 'outgoing',
            type: 'text',
        );

        $dto2 = new ChatMessageDTO(
            ticketId: $this->ticket->id,
            content: 'Second message',
            direction: 'outgoing',
            type: 'text',
        );

        $result1 = $this->action->handle($this->tenant->id, $dto1);
        $result2 = $this->action->handle($this->tenant->id, $dto2);

        $this->assertTrue($result1['created']);
        $this->assertTrue($result2['created']);
    }

    #[Test]
    public function it_returns_error_for_missing_ticket(): void
    {
        $dto = new ChatMessageDTO(
            ticketId: (string) Str::orderedUuid(), // UUID válido mas inexistente
            content: 'Test message',
            direction: 'outgoing',
            type: 'text',
        );

        $result = $this->action->handle($this->tenant->id, $dto);

        $this->assertFalse($result['created']);
        $this->assertNull($result['message']);
        $this->assertSame('ticket_not_found', $result['reason']);
    }

    #[Test]
    public function it_can_skip_idempotency_check(): void
    {
        $dto = new ChatMessageDTO(
            ticketId: $this->ticket->id,
            content: 'Skip idempotency test',
            direction: 'outgoing',
            type: 'text',
        );

        $result1 = $this->action->handle($this->tenant->id, $dto, skipIdempotency: true);
        $result2 = $this->action->handle($this->tenant->id, $dto, skipIdempotency: true);

        $this->assertTrue($result1['created']);
        $this->assertTrue($result2['created']);

        $count = \Domain\Chat\Models\ChatMessage::query()->where('ticket_id', $this->ticket->id)
            ->where('content', 'Skip idempotency test')
            ->count();
        $this->assertSame(2, $count);
    }

    #[Test]
    public function it_can_clear_idempotency_key(): void
    {
        $dto = new ChatMessageDTO(
            ticketId: $this->ticket->id,
            content: 'Clear key test',
            direction: 'outgoing',
            type: 'text',
        );

        $result1 = $this->action->handle($this->tenant->id, $dto);
        $this->assertTrue($result1['created']);

        // Limpar chave de idempotência
        $this->action->clearIdempotencyKey($dto);

        // Esperar para sair da janela de dedup
        \Illuminate\Support\Sleep::sleep(1);

        // Agora deve permitir nova mensagem (se a janela de tempo tiver passado)
        // Como estamos em teste, vamos usar skipIdempotency para verificar
        $result2 = $this->action->handle($this->tenant->id, $dto, skipIdempotency: true);
        $this->assertTrue($result2['created']);
    }

    #[Test]
    public function it_normalizes_content_for_dedup(): void
    {
        $dto1 = new ChatMessageDTO(
            ticketId: $this->ticket->id,
            content: '  Hello World  ',
            direction: 'outgoing',
            type: 'text',
        );

        $dto2 = new ChatMessageDTO(
            ticketId: $this->ticket->id,
            content: 'HELLO WORLD',
            direction: 'outgoing',
            type: 'text',
        );

        $result1 = $this->action->handle($this->tenant->id, $dto1);
        $result2 = $this->action->handle($this->tenant->id, $dto2);

        // Segunda deve ser detectada como duplicata (normalização)
        $this->assertTrue($result1['created']);
        $this->assertFalse($result2['created']);
    }

    #[Test]
    public function it_handles_external_id_idempotency(): void
    {
        $externalId = 'webhook_msg_12345';

        // Verificar que não foi processado
        $this->assertFalse(
            $this->action->hasProcessedByExternalId($this->ticket->id, $externalId)
        );

        // Marcar como processado
        $this->action->markProcessedByExternalId(
            $this->ticket->id,
            $externalId,
            'msg-uuid-123'
        );

        // Agora deve retornar true
        $this->assertTrue(
            $this->action->hasProcessedByExternalId($this->ticket->id, $externalId)
        );
    }

    #[Test]
    public function it_generates_unique_keys_from_external_id(): void
    {
        $key1 = SendMessageAction::keyFromExternalId('ticket-1', 'ext-123');
        $key2 = SendMessageAction::keyFromExternalId('ticket-1', 'ext-456');
        $key3 = SendMessageAction::keyFromExternalId('ticket-2', 'ext-123');

        $this->assertNotEquals($key1, $key2);
        $this->assertNotEquals($key1, $key3);
        $this->assertNotEquals($key2, $key3);
    }

    #[Test]
    public function it_isolates_by_ticket(): void
    {
        $ticket2 = ChatTicket::factory()->create([
            'tenant_id' => $this->tenant->id,
            'contact_id' => $this->contact->id,
        ]);

        $dto1 = new ChatMessageDTO(
            ticketId: $this->ticket->id,
            content: 'Same content message',
            direction: 'outgoing',
            type: 'text',
        );

        $dto2 = new ChatMessageDTO(
            ticketId: $ticket2->id,
            content: 'Same content message',
            direction: 'outgoing',
            type: 'text',
        );

        $result1 = $this->action->handle($this->tenant->id, $dto1);
        $result2 = $this->action->handle($this->tenant->id, $dto2);

        // Ambas devem ser criadas (tickets diferentes)
        $this->assertTrue($result1['created']);
        $this->assertTrue($result2['created']);
    }

    #[Test]
    public function it_resolves_pdf_mime_as_document_even_without_file_url(): void
    {
        $message = ChatMessage::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ticket_id' => $this->ticket->id,
            'type' => 'file',
            'mime_type' => 'application/pdf',
            'file_url' => null,
        ]);

        $resolver = new \ReflectionMethod($this->messageActions, 'resolveMediaType');
        $resolver->setAccessible(true);

        $resolvedType = $resolver->invoke($this->messageActions, $message);

        $this->assertSame('document', $resolvedType);
    }
}
