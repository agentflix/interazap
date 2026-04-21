<?php

declare(strict_types=1);

use Domain\Chat\Actions\EvaluateTicketCsatAction;
use Domain\Chat\Actions\SendTicketMessageAction;
use Domain\Chat\Actions\UpdateChatTicketAction;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatActivityBroadcastService;
use Domain\Chat\Services\ChatBroadcastService;
use Domain\Chat\Services\ChatGatewayService;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Services\GatewayBroadcastService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->tenant = PlatformTenant::factory()->create();

    $this->gateway = \Mockery::mock(ChatGatewayService::class);
    $this->gateway->shouldIgnoreMissing();

    $this->gatewayBroadcast = \Mockery::mock(GatewayBroadcastService::class);
    $chatBroadcastService = new ChatBroadcastService($this->gatewayBroadcast);
    $this->activityBroadcast = new ChatActivityBroadcastService($chatBroadcastService);

    $this->messageAction = new SendTicketMessageAction($this->gateway);
    $this->evaluateAction = new EvaluateTicketCsatAction($this->gateway);

    $this->action = new UpdateChatTicketAction(
        $this->gateway,
        $this->activityBroadcast,
        $this->messageAction,
        $this->evaluateAction,
    );
});

afterEach(function (): void {
    \Mockery::close();
});

it('emits ticket.updated with ticket_closed payload when status is closed', function (): void {
    $ticket = ChatTicket::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'status' => 'open',
        'closed_at' => null,
        'instance_id' => null,
    ]);

    $this->gatewayBroadcast
        ->shouldReceive('broadcastEvent')
        ->once()
        ->withArgs(function (string $event, array $data, ?string $room) use ($ticket): bool {
            return $event === 'chat.activity'
                && $room === 'ticket:'.(string) $ticket->id
                && (($data['subevents'][0]['type'] ?? null) === 'ticket.updated')
                && (($data['subevents'][0]['data']['ticket_id'] ?? null) === (string) $ticket->id)
                && (($data['subevents'][0]['data']['tenant_id'] ?? null) === (string) $ticket->tenant_id)
                && (($data['subevents'][0]['data']['event_type'] ?? null) === 'ticket_closed')
                && (($data['subevents'][0]['data']['ticket']['status'] ?? null) === 'closed');
        });

    $this->gatewayBroadcast
        ->shouldReceive('broadcastEvent')
        ->once()
        ->withArgs(function (string $event, array $data, ?string $room) use ($ticket): bool {
            return $event === 'chat.activity'
                && $room === 'tenant:'.(string) $ticket->tenant_id
                && (($data['subevents'][0]['type'] ?? null) === 'ticket.updated');
        });

    $updated = $this->action->updateStatus($ticket, 'closed', null, 'normal', null);

    expect($updated->status)->toBe('closed')
        ->and($updated->closed_at)->not->toBeNull();
});

it('uses configured end service message when closing in normal mode', function (): void {
    $instance = ChatInstance::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'provider' => 'uazapi',
        'evaluation_enabled' => false,
        'settings_json' => [
            'send_end_service_message' => true,
            'end_service_message' => 'Atendimento finalizado automaticamente.',
            'token' => 'token-end-service',
        ],
    ]);

    $ticket = ChatTicket::factory()->create([
        'tenant_id' => (string) $this->tenant->id,
        'status' => 'open',
        'closed_at' => null,
        'instance_id' => (string) $instance->id,
        'phone' => '+55 11 99999-1111',
    ]);

    $this->gateway
        ->shouldReceive('sendText')
        ->once()
        ->with('token-end-service', [
            'number' => '5511999991111',
            'text' => 'Atendimento finalizado automaticamente.',
        ])
        ->andReturn(['id' => 'msg-end-service']);

    $this->gatewayBroadcast
        ->shouldReceive('broadcastEvent')
        ->twice();

    $updated = $this->action->updateStatus($ticket, 'closed', null, 'normal', null);

    expect($updated->status)->toBe('closed')
        ->and($updated->closed_mode)->toBe('normal')
        ->and($updated->closed_at)->not->toBeNull();
});
