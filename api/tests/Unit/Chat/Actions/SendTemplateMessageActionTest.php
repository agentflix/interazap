<?php

declare(strict_types=1);

use Domain\Chat\Actions\ProcessChatMessageAction;
use Domain\Chat\Actions\SendTemplateMessageAction;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatMessageTemplate;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatActivityBroadcastService;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    config()->set('gateway.url', 'http://gateway.test');
    config()->set('gateway.secret', 'secret-key');

    $this->tenant = PlatformTenant::factory()->create();
    $this->instance = ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'meta',
    ]);
    $this->contact = CRMContact::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'phone' => '5511999999999',
    ]);
    $this->ticket = ChatTicket::factory()->forTenant((string) $this->tenant->id)->create([
        'instance_id' => (string) $this->instance->id,
        'contact_id' => (string) $this->contact->id,
        'phone_e164' => '5511999999999',
    ]);
    $this->template = ChatMessageTemplate::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'chat_instance_id' => (string) $this->instance->id,
        'provider' => 'meta',
        'name' => 'welcome_v1',
        'language' => 'pt_BR',
        'status' => 'approved',
        'components_json' => [
            ['type' => 'BODY', 'text' => 'Olá {{1}}, seu pedido {{2}} está pronto!'],
        ],
    ]);

    $this->action = new SendTemplateMessageAction(
        new ProcessChatMessageAction(app(ChatActivityBroadcastService::class)),
    );
});

it('envia template aprovado e cria ChatMessage status=sent', function (): void {
    Http::fake([
        '*/channels/*/send-template' => Http::response([
            'messageid' => 'wamid.HBgL999',
            'status' => 'sent',
        ], 200),
    ]);

    $message = $this->action->execute(
        tenantId: (string) $this->tenant->id,
        ticketId: (string) $this->ticket->id,
        templateId: (string) $this->template->id,
        variables: ['1' => 'Rafael', '2' => '#1234'],
    );

    expect($message)->toBeInstanceOf(ChatMessage::class)
        ->and($message->type)->toBe('template')
        ->and($message->direction)->toBe('outgoing')
        ->and($message->source)->toBe('agent')
        ->and($message->status)->toBe('sent')
        ->and($message->external_id)->toBe('wamid.HBgL999')
        ->and($message->content)->toBe('Olá Rafael, seu pedido #1234 está pronto!')
        ->and($message->metadata['template']['name'])->toBe('welcome_v1')
        ->and($message->metadata['template_variables'])->toBe(['1' => 'Rafael', '2' => '#1234']);

    Http::assertSent(function (array $request): bool {
        if (! str_contains($request->url(), '/send-template')) {
            return false;
        }

        return $request['to'] === '5511999999999'
            && $request['template_name'] === 'welcome_v1'
            && $request['language'] === 'pt_BR';
    });
});

it('falha quando template não está aprovado', function (): void {
    $this->template->update(['status' => 'pending']);

    $this->action->execute(
        tenantId: (string) $this->tenant->id,
        ticketId: (string) $this->ticket->id,
        templateId: (string) $this->template->id,
    );
})->throws(ValidationException::class);

it('falha quando ticket não é Meta', function (): void {
    $uazapi = ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'uazapi',
    ]);
    $this->ticket->update(['instance_id' => (string) $uazapi->id]);

    $this->action->execute(
        tenantId: (string) $this->tenant->id,
        ticketId: (string) $this->ticket->id,
        templateId: (string) $this->template->id,
    );
})->throws(ValidationException::class);

it('marca message como failed quando gateway falha', function (): void {
    Http::fake([
        '*/channels/*/send-template' => Http::response(['error' => 'rate_limit'], 429),
    ]);

    $message = $this->action->execute(
        tenantId: (string) $this->tenant->id,
        ticketId: (string) $this->ticket->id,
        templateId: (string) $this->template->id,
    );

    expect($message->status)->toBe('failed')
        ->and($message->error_message)->toContain('429');
});
