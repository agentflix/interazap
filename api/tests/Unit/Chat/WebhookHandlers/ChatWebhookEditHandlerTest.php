<?php

declare(strict_types=1);

namespace Tests\Unit\Chat\WebhookHandlers;

use Domain\Chat\Actions\WebhookHandlers\ChatWebhookEditHandler;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatBroadcastService;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Services\GatewayBroadcastService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChatWebhookEditHandlerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_prioritizes_edited_reference_and_broadcasts_edited_at(): void
    {
        config()->set('services.gateway.realtime_pubsub_enabled', false);
        config()->set('services.gateway.realtime_http_fallback_enabled', true);
        Http::fake();

        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $ticket = ChatTicket::factory()->forTenant($tenantId)->create();
        $targetMessage = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'external_id' => 'original-message-id',
            'content' => 'Mensagem original',
            'edit_history' => [],
            'is_edited' => false,
            'edited_at' => null,
        ]);
        $wrapperMessage = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'external_id' => 'wrapper-event-id',
            'content' => 'Wrapper deve permanecer intacto',
            'edit_history' => [],
            'is_edited' => false,
            'edited_at' => null,
        ]);

        $broadcastService = new ChatBroadcastService(new GatewayBroadcastService);

        try {
            \Illuminate\Support\Facades\Date::setTestNow('2026-03-17 14:00:00');

            $handler = new ChatWebhookEditHandler($broadcastService);
            $handler->handle($tenantId, [
                'message' => [
                    'id' => 'wrapper-event-id',
                    'edited' => 'original-message-id',
                    'body' => 'Mensagem editada',
                ],
            ]);

            $targetMessage->refresh();
            $wrapperMessage->refresh();

            $this->assertSame('Mensagem editada', $targetMessage->content);
            $this->assertTrue($targetMessage->is_edited);
            $this->assertSame('2026-03-17T14:00:00+00:00', $targetMessage->edited_at?->toIso8601String());
            $this->assertCount(1, $targetMessage->edit_history ?? []);

            $this->assertSame('Wrapper deve permanecer intacto', $wrapperMessage->content);
            $this->assertFalse($wrapperMessage->is_edited);
            $this->assertNull($wrapperMessage->edited_at);

            Http::assertSent(function ($request) use ($targetMessage, $ticket, $tenantId): bool {
                $data = $request->data();

                return str_contains((string) $request->url(), '/internal/broadcast/event')
                    && ($data['event'] ?? null) === 'chat.message.edit'
                    && ($data['room'] ?? null) === 'tenant:'.$tenantId
                    && ($data['data']['message_id'] ?? null) === (string) $targetMessage->id
                    && ($data['data']['ticket_id'] ?? null) === (string) $ticket->id
                    && ($data['data']['tenant_id'] ?? null) === $tenantId
                    && ($data['data']['content'] ?? null) === 'Mensagem editada'
                    && ($data['data']['is_edited'] ?? null) === true
                    && ($data['data']['edited_at'] ?? null) === '2026-03-17T14:00:00+00:00';
            });
        } finally {
            \Illuminate\Support\Facades\Date::setTestNow();
        }
    }

    public function test_it_skips_edit_when_content_does_not_change(): void
    {
        config()->set('services.gateway.realtime_pubsub_enabled', false);
        config()->set('services.gateway.realtime_http_fallback_enabled', true);
        Http::fake();

        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $ticket = ChatTicket::factory()->forTenant($tenantId)->create();
        $message = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'external_id' => 'message-no-op',
            'content' => 'Conteúdo igual',
            'edit_history' => [],
            'is_edited' => false,
            'edited_at' => null,
        ]);

        $broadcastService = new ChatBroadcastService(new GatewayBroadcastService);

        $handler = new ChatWebhookEditHandler($broadcastService);
        $handler->handle($tenantId, [
            'message' => [
                'id' => 'wrapper-no-op',
                'edited' => 'message-no-op',
                'body' => 'Conteúdo igual',
            ],
        ]);

        $message->refresh();

        $this->assertSame('Conteúdo igual', $message->content);
        $this->assertFalse($message->is_edited);
        $this->assertNull($message->edited_at);
        $this->assertSame([], $message->edit_history ?? []);
        Http::assertNotSent(function ($request): bool {
            $data = $request->data();

            return ($data['event'] ?? null) === 'chat.message.edit';
        });
    }

    public function test_it_does_not_edit_envelope_message_when_only_fallback_references_are_available(): void
    {
        config()->set('services.gateway.realtime_pubsub_enabled', false);
        config()->set('services.gateway.realtime_http_fallback_enabled', true);
        Http::fake();

        $tenant = PlatformTenant::factory()->create();
        $tenantId = (string) $tenant->id;

        $ticket = ChatTicket::factory()->forTenant($tenantId)->create();
        $targetMessage = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'external_id' => 'actual-message-id',
            'content' => 'Mensagem original',
            'edit_history' => [],
            'is_edited' => false,
            'edited_at' => null,
        ]);
        $envelopeMessage = ChatMessage::factory()->create([
            'tenant_id' => $tenantId,
            'ticket_id' => $ticket->id,
            'external_id' => 'envelope-event-id',
            'content' => 'Envelope deve permanecer intacto',
            'edit_history' => [],
            'is_edited' => false,
            'edited_at' => null,
        ]);

        $broadcastService = new ChatBroadcastService(new GatewayBroadcastService);

        $handler = new ChatWebhookEditHandler($broadcastService);
        $handler->handle($tenantId, [
            'message_id' => 'actual-message-id',
            'message' => [
                'id' => 'envelope-event-id',
                'body' => 'Mensagem editada corretamente',
            ],
        ]);

        $targetMessage->refresh();
        $envelopeMessage->refresh();

        $this->assertSame('Mensagem editada corretamente', $targetMessage->content);
        $this->assertTrue($targetMessage->is_edited);
        $this->assertCount(1, $targetMessage->edit_history ?? []);

        $this->assertSame('Envelope deve permanecer intacto', $envelopeMessage->content);
        $this->assertFalse($envelopeMessage->is_edited);
        $this->assertNull($envelopeMessage->edited_at);

        Http::assertSent(function ($request) use ($targetMessage): bool {
            $data = $request->data();

            return ($data['event'] ?? null) === 'chat.message.edit'
                && ($data['data']['message_id'] ?? null) === (string) $targetMessage->id
                && ($data['data']['content'] ?? null) === 'Mensagem editada corretamente';
        });
    }
}
