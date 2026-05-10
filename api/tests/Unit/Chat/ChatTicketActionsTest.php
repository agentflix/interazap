<?php

declare(strict_types=1);

use Domain\Ai\Jobs\AiAnalyzeSentimentJob;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Actions\ChatTicketActions;
use Domain\Chat\DTOs\ChatTicketDTO;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Models\ChatTicketEvaluation;
use Domain\Chat\Services\ChatActivityBroadcastService;
use Domain\Chat\Services\ChatBroadcastService;
use Domain\Chat\Services\ChatGatewayService;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Services\GatewayBroadcastService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

uses(LazilyRefreshDatabase::class);

beforeEach(function (): void {
    $this->gateway = \Mockery::mock(ChatGatewayService::class);
    $this->gateway->shouldIgnoreMissing();

    Http::fake();

    $gatewayBroadcast = new GatewayBroadcastService;
    $broadcast = new ChatBroadcastService($gatewayBroadcast);

    $this->activityBroadcast = new ChatActivityBroadcastService($broadcast);
    $this->actions = new ChatTicketActions(
        $this->gateway,
        $this->activityBroadcast,
    );
    $this->tenant = PlatformTenant::factory()->create();
});

afterEach(function (): void {
    \Mockery::close();
});

it('creates ticket and auto creates contact when contact id is missing', function (): void {
    $dto = ChatTicketDTO::fromArray([
        'channel' => 'whatsapp',
        'phone' => '+55 11 98888-7766',
        'remote_jid' => '5511988887766@s.whatsapp.net',
        'subject' => 'Assistência',
        'push_name' => 'Cliente Teste',
    ]);

    $ticket = $this->actions->create((string) $this->tenant->id, $dto);

    expect($ticket)->toBeInstanceOf(ChatTicket::class)
        ->and($ticket->tenant_id)->toBe((string) $this->tenant->id)
        ->and($ticket->contact_id)->not->toBeNull()
        ->and(CRMContact::query()->where('tenant_id', $this->tenant->id)->count())->toBe(1);
});

it('lists tickets filtering by tenant and status', function (): void {
    ChatTicket::factory()->count(2)->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'pending',
    ]);

    ChatTicket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'closed',
    ]);

    ChatTicket::factory()->create(['tenant_id' => PlatformTenant::factory()->create()->id]);

    $paginator = $this->actions->list((string) $this->tenant->id, ['status' => 'pending']);

    expect($paginator)->toBeInstanceOf(LengthAwarePaginator::class)
        ->and($paginator->total())->toBe(2);
});

it('updates status to closed, stores reason and creates evaluation when enabled', function (): void {
    $instance = ChatInstance::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $instance->evaluation_enabled = true;
    $instance->save();

    $ticket = ChatTicket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'instance_id' => $instance->id,
        'status' => 'open',
    ]);

    $updated = $this->actions->updateStatus($ticket, 'closed', 'done');

    expect($updated->status)->toBe('closed')
        ->and($updated->close_reason)->toBe('done')
        ->and(ChatTicketEvaluation::query()->where('ticket_id', $ticket->id)->exists())->toBeTrue();
});

it('transfers pending ticket to user and opens it automatically', function (): void {
    $ticket = ChatTicket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'pending',
        'assigned_to' => null,
    ]);

    $user = AuthUser::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $result = $this->actions->transfer($ticket, (string) $user->id, null);

    expect($result->assigned_to)->toBe((string) $user->id)
        ->and($result->status)->toBe('open');
});

it('reuses existing ticket matching remote jid before creating new one', function (): void {
    $ticket = ChatTicket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'remote_jid' => '5511988887766@s.whatsapp.net',
        'status' => 'open',
    ]);

    $dto = ChatTicketDTO::fromArray([
        'channel' => 'whatsapp',
        'remote_jid' => '5511988887766@s.whatsapp.net',
        'subject' => 'Follow up',
    ]);

    $found = $this->actions->findOrCreateByRemoteJid((string) $this->tenant->id, $dto);

    expect($found->id)->toBe($ticket->id)
        ->and(ChatTicket::query()->count())->toBe(1);
});

it('ensures find uses tenant isolation', function (): void {
    $ticket = ChatTicket::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

    $otherTenant = PlatformTenant::factory()->create();
    $this->actions->find((string) $otherTenant->id, (string) $ticket->id);
});

it('sends start service automated message when opening ticket', function (): void {
    $this->gateway
        ->shouldReceive('sendText')
        ->once()
        ->andReturn(['id' => 'msg-start']);

    $instance = ChatInstance::factory()->create([
        'tenant_id' => $this->tenant->id,
        'provider' => 'uazapi',
        'settings_json' => [
            'send_start_service_message' => true,
            'start_service_message' => 'Atendimento iniciado.',
            'token' => 'token-start',
        ],
    ]);

    $ticket = ChatTicket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'instance_id' => $instance->id,
        'status' => 'pending',
        'phone' => '+55 11 99999-0001',
    ]);

    $user = AuthUser::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actions->open((string) $this->tenant->id, (string) $ticket->id, (string) $user->id);

    $automated = \Domain\Chat\Models\ChatMessage::query()
        ->where('ticket_id', $ticket->id)
        ->where('source', 'system')
        ->where('content', 'Atendimento iniciado.')
        ->first();

    expect($automated)->not->toBeNull()
        ->and($automated?->status)->toBe('sent');
});

it('sends end service automated message when closing ticket', function (): void {
    Bus::fake();
    $plan = PlatformPlan::factory()->create(['ai_enabled' => true]);
    $this->tenant->update(['plan_id' => $plan->id]);

    $this->gateway
        ->shouldReceive('sendText')
        ->once()
        ->andReturn(['id' => 'msg-end']);

    $instance = ChatInstance::factory()->create([
        'tenant_id' => $this->tenant->id,
        'provider' => 'uazapi',
        'settings_json' => [
            'send_end_service_message' => true,
            'end_service_message' => 'Atendimento finalizado.',
            'token' => 'token-end',
        ],
    ]);

    $ticket = ChatTicket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'instance_id' => $instance->id,
        'status' => 'open',
        'phone' => '+55 11 99999-0002',
    ]);

    \Domain\Chat\Models\ChatMessage::query()->create([
        'tenant_id' => (string) $this->tenant->id,
        'ticket_id' => (string) $ticket->id,
        'content' => 'atendimento muito ruim',
        'type' => 'text',
        'direction' => 'incoming',
        'is_from_contact' => true,
        'status' => 'received',
    ]);

    $this->actions->updateStatus($ticket, 'closed', 'done');

    $automated = \Domain\Chat\Models\ChatMessage::query()
        ->where('ticket_id', $ticket->id)
        ->where('source', 'system')
        ->where('content', 'Atendimento finalizado.')
        ->first();

    expect($automated)->not->toBeNull()
        ->and($automated?->status)->toBe('sent');

    Bus::assertDispatched(AiAnalyzeSentimentJob::class);
});

it('does not dispatch final sentiment when tenant plan has ai disabled', function (): void {
    Bus::fake();

    $plan = PlatformPlan::factory()->create(['ai_enabled' => false]);
    $this->tenant->update(['plan_id' => $plan->id]);

    $ticket = ChatTicket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'open',
    ]);

    \Domain\Chat\Models\ChatMessage::query()->create([
        'tenant_id' => (string) $this->tenant->id,
        'ticket_id' => (string) $ticket->id,
        'content' => 'texto para análise final',
        'type' => 'text',
        'direction' => 'incoming',
        'is_from_contact' => true,
        'status' => 'received',
    ]);

    $this->actions->updateStatus($ticket, 'closed', 'done', 'forced');

    Bus::assertNotDispatched(AiAnalyzeSentimentJob::class);
});

it('does not dispatch final sentiment without inbound text', function (): void {
    Bus::fake();

    $ticket = ChatTicket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'status' => 'open',
    ]);

    $this->actions->updateStatus($ticket, 'closed', 'done', 'forced');

    Bus::assertNotDispatched(AiAnalyzeSentimentJob::class);
});

it('sends department transfer automated message when enabled', function (): void {
    $this->gateway
        ->shouldReceive('sendText')
        ->once()
        ->andReturn(['id' => 'msg-transfer']);

    $instance = ChatInstance::factory()->create([
        'tenant_id' => $this->tenant->id,
        'mode' => 'production',
        'provider' => 'uazapi',
        'settings_json' => [
            'send_department_transfer_message' => true,
            'department_transfer_message' => 'Transferimos você para outro setor.',
            'token' => 'token-transfer',
        ],
    ]);

    $ticket = ChatTicket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'instance_id' => $instance->id,
        'status' => 'open',
        'phone' => '+55 11 99999-0003',
    ]);

    $this->actions->transfer($ticket, null, (string) \Illuminate\Support\Str::orderedUuid());

    $automated = \Domain\Chat\Models\ChatMessage::query()
        ->where('ticket_id', $ticket->id)
        ->where('source', 'system')
        ->where('content', 'Transferimos você para outro setor.')
        ->first();

    expect($automated)->not->toBeNull()
        ->and($automated?->status)->toBe('sent');
});

it('does not send department transfer message in ai business hours mode', function (): void {
    $this->gateway
        ->shouldReceive('sendText')
        ->never();

    $instance = ChatInstance::factory()->create([
        'tenant_id' => $this->tenant->id,
        'mode' => 'ia_horario',
        'provider' => 'uazapi',
        'settings_json' => [
            'send_department_transfer_message' => true,
            'department_transfer_message' => 'Transferimos você para outro setor.',
            'token' => 'token-transfer-skip',
        ],
    ]);

    $ticket = ChatTicket::factory()->create([
        'tenant_id' => $this->tenant->id,
        'instance_id' => $instance->id,
        'status' => 'open',
        'phone' => '+55 11 99999-0004',
    ]);

    $this->actions->transfer($ticket, null, (string) \Illuminate\Support\Str::orderedUuid());

    $automatedCount = \Domain\Chat\Models\ChatMessage::query()
        ->where('ticket_id', $ticket->id)
        ->where('source', 'system')
        ->where('content', 'Transferimos você para outro setor.')
        ->count();

    expect($automatedCount)->toBe(0);
});
