<?php

declare(strict_types=1);

use Domain\Chat\Actions\SendChatMessageAction;
use Domain\Chat\DTOs\ChatMessageDTO;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config()->set('gateway.url', 'http://gateway.test');
    config()->set('gateway.secret', 'secret-key');
    Http::fake();

    $this->tenant = PlatformTenant::factory()->create();
    $this->action = $this->app->make(SendChatMessageAction::class);
});

function makeMetaTicketWithLastIncoming(string $tenantId, ?int $hoursAgo): array
{
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $tenantId,
        'provider' => 'meta',
    ]);
    $contact = CRMContact::factory()->create([
        'tenant_id' => $tenantId,
        'phone' => '5511988887777',
    ]);
    $ticket = ChatTicket::factory()->forTenant($tenantId)->create([
        'instance_id' => $instance->id,
        'contact_id' => $contact->id,
        'phone_e164' => '5511988887777',
    ]);

    if ($hoursAgo !== null) {
        $msg = ChatMessage::query()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'contact_id' => $contact->id,
            'direction' => 'incoming',
            'is_from_contact' => true,
            'source' => ChatMessageDTO::SOURCE_WEBHOOK,
            'type' => 'text',
            'content' => 'Mensagem do contato',
            'status' => 'received',
            'received_at' => now()->subHours($hoursAgo),
        ]);
        $msg->created_at = now()->subHours($hoursAgo);
        $msg->save();
    }

    return ['instance' => $instance, 'contact' => $contact, 'ticket' => $ticket];
}

it('bloqueia mensagem livre fora da janela 24h em canal Meta', function (): void {
    $ctx = makeMetaTicketWithLastIncoming((string) $this->tenant->id, hoursAgo: 30);

    $dto = ChatMessageDTO::fromArray([
        'ticket_id' => (string) $ctx['ticket']->id,
        'contact_id' => (string) $ctx['contact']->id,
        'direction' => 'outgoing',
        'is_from_contact' => false,
        'source' => ChatMessageDTO::SOURCE_AGENT,
        'type' => 'text',
        'content' => 'Olá novamente',
    ]);

    $this->action->create((string) $this->tenant->id, $dto);
})->throws(ValidationException::class);

it('permite mensagem dentro da janela 24h em canal Meta', function (): void {
    $ctx = makeMetaTicketWithLastIncoming((string) $this->tenant->id, hoursAgo: 1);

    $dto = ChatMessageDTO::fromArray([
        'ticket_id' => (string) $ctx['ticket']->id,
        'contact_id' => (string) $ctx['contact']->id,
        'direction' => 'outgoing',
        'is_from_contact' => false,
        'source' => ChatMessageDTO::SOURCE_AGENT,
        'type' => 'text',
        'content' => 'Olá novamente',
    ]);

    $message = $this->action->create((string) $this->tenant->id, $dto);
    expect($message)->toBeInstanceOf(ChatMessage::class);
});

it('não aplica guard quando type=template (template reabre janela)', function (): void {
    $ctx = makeMetaTicketWithLastIncoming((string) $this->tenant->id, hoursAgo: 30);

    $dto = ChatMessageDTO::fromArray([
        'ticket_id' => (string) $ctx['ticket']->id,
        'contact_id' => (string) $ctx['contact']->id,
        'direction' => 'outgoing',
        'is_from_contact' => false,
        'source' => ChatMessageDTO::SOURCE_AGENT,
        'type' => 'template',
        'content' => 'Olá',
    ]);

    $message = $this->action->create((string) $this->tenant->id, $dto);
    expect($message)->toBeInstanceOf(ChatMessage::class);
});

it('não aplica guard em canal não-Meta (uazapi)', function (): void {
    $instance = ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'uazapi',
    ]);
    $contact = CRMContact::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'phone' => '5511988887777',
    ]);
    $ticket = ChatTicket::factory()->forTenant((string) $this->tenant->id)->create([
        'instance_id' => $instance->id,
        'contact_id' => $contact->id,
        'phone_e164' => '5511988887777',
    ]);
    ChatMessage::query()->create([
        'tenant_id' => (string) $this->tenant->id,
        'ticket_id' => $ticket->id,
        'contact_id' => $contact->id,
        'direction' => 'incoming',
        'is_from_contact' => true,
        'source' => ChatMessageDTO::SOURCE_WEBHOOK,
        'type' => 'text',
        'content' => 'Mensagem',
        'status' => 'received',
    ])->forceFill(['created_at' => now()->subDays(3)])->save();

    $dto = ChatMessageDTO::fromArray([
        'ticket_id' => (string) $ticket->id,
        'contact_id' => (string) $contact->id,
        'direction' => 'outgoing',
        'is_from_contact' => false,
        'source' => ChatMessageDTO::SOURCE_AGENT,
        'type' => 'text',
        'content' => 'Olá',
    ]);

    $message = $this->action->create((string) $this->tenant->id, $dto);
    expect($message)->toBeInstanceOf(ChatMessage::class);
});
