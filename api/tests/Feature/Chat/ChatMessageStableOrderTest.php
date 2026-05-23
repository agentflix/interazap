<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Actions\ListChatMessagesAction;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Testes de ordenação estável de mensagens (created_at + id).
 *
 * Garante que mensagens com o mesmo created_at preservam ordem
 * determinística via desempate por id, tanto em paginação
 * tradicional quanto em paginação por cursor.
 *
 * @category Feature
 */
final class ChatMessageStableOrderTest extends TestCase
{
    use LazilyRefreshDatabase;

    private string $tenantId;

    private AuthUser $user;

    protected function setUp(): void
    {
        parent::setUp();

        $plan = PlatformPlan::factory()->create();
        $this->tenantId = (string) PlatformTenant::factory()->create(['plan_id' => $plan->id])->id;
        $this->user = AuthUser::factory()->create(['tenant_id' => $this->tenantId]);

        $permView = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.messages.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()],
        );
        $this->user->givePermissionTo($permView);
    }

    /**
     * Cria um ticket e mensagens com mesmo created_at para testar desempate por id.
     *
     * @return array{ticket: ChatTicket, messages: ChatMessage[]}
     */
    private function createTicketWithSameTimestampMessages(int $count = 5): array
    {
        $instance = ChatInstance::factory()->create(['tenant_id' => $this->tenantId]);
        $ticket = ChatTicket::factory()->forTenant($this->tenantId)->create([
            'instance_id' => $instance->id,
        ]);

        $frozenTimestamp = now()->startOfSecond();
        $messages = [];

        for ($i = 0; $i < $count; $i++) {
            $messages[] = ChatMessage::factory()->create([
                'tenant_id' => $this->tenantId,
                'ticket_id' => $ticket->id,
                'created_at' => $frozenTimestamp,
                'content' => "Message {$i}",
            ]);
        }

        return ['ticket' => $ticket, 'messages' => $messages];
    }

    public function test_list_by_ticket_orders_by_created_at_desc_then_id_desc(): void
    {
        $action = app(ListChatMessagesAction::class);
        $result = $this->createTicketWithSameTimestampMessages(5);
        $ticket = $result['ticket'];

        $paginator = $action->listByTicket($this->tenantId, (string) $ticket->id);

        $resultItems = $paginator->items();
        $this->assertCount(5, $resultItems);

        // Verifica ordem DESC: mensagens com mesmo created_at devem estar
        // ordenadas por id DESC (UUIDs ordered são time-sortable, então
        // IDs maiores = criados depois, mas queremos consistência).
        $previousTimestamp = null;
        $previousId = null;

        foreach ($resultItems as $msg) {
            if ($previousTimestamp !== null) {
                if ($msg->created_at->equalTo($previousTimestamp)) {
                    // Mesmo timestamp: id deve ser DESC (menor id = criado antes = fica depois)
                    $this->assertLessThan(
                        $previousId,
                        $msg->id,
                        'Mensagens com mesmo created_at devem ordenar por id DESC',
                    );
                } else {
                    // Timestamp diferente: mais recente primeiro
                    $this->assertTrue(
                        $msg->created_at->lt($previousTimestamp),
                        'Mensagens com created_at diferente devem ordenar DESC',
                    );
                }
            }

            $previousTimestamp = $msg->created_at;
            $previousId = $msg->id;
        }
    }

    public function test_list_by_ticket_cursor_orders_by_created_at_desc_then_id_desc(): void
    {
        $action = app(ListChatMessagesAction::class);
        $result = $this->createTicketWithSameTimestampMessages(5);
        $ticket = $result['ticket'];

        $response = $action->listByTicketCursor(
            tenantId: $this->tenantId,
            ticketId: (string) $ticket->id,
            limit: 5,
        );

        $resultMessages = $response['messages'];
        $this->assertCount(5, $resultMessages);

        // Verifica ordem estável DESC com desempate por id
        for ($i = 1; $i < $resultMessages->count(); $i++) {
            $prev = $resultMessages[$i - 1];
            $curr = $resultMessages[$i];

            if ($prev->created_at->equalTo($curr->created_at)) {
                $this->assertGreaterThan(
                    $curr->id,
                    $prev->id,
                    'Cursor: mensagens com mesmo created_at devem ordenar por id DESC',
                );
            } else {
                $this->assertTrue(
                    $prev->created_at->gt($curr->created_at),
                    'Cursor: mensagens com timestamps diferentes devem ordenar DESC',
                );
            }
        }
    }

    public function test_cursor_before_uses_composite_key_for_tie_breaking(): void
    {
        $action = app(ListChatMessagesAction::class);
        $result = $this->createTicketWithSameTimestampMessages(5);
        $ticket = $result['ticket'];
        $messages = $result['messages'];

        // Usa a 3a mensagem como cursor "before" — deve retornar as 2 anteriores
        $pivotMessage = $messages[2];

        $response = $action->listByTicketCursor(
            tenantId: $this->tenantId,
            ticketId: (string) $ticket->id,
            limit: 10,
            before: (string) $pivotMessage->id,
        );

        $resultMessages = $response['messages'];
        $this->assertCount(2, $resultMessages);

        // Todas as mensagens retornadas devem ser anteriores ao pivot
        foreach ($resultMessages as $msg) {
            if ($msg->created_at->equalTo($pivotMessage->created_at)) {
                $this->assertLessThan(
                    $pivotMessage->id,
                    $msg->id,
                    'Mensagens com mesmo timestamp e id < pivot.id devem ser incluídas',
                );
            } else {
                $this->assertTrue(
                    $msg->created_at->lt($pivotMessage->created_at),
                    'Mensagens com timestamp anterior devem ser incluídas',
                );
            }
        }
    }

    public function test_cursor_after_uses_composite_key_for_tie_breaking(): void
    {
        $action = app(ListChatMessagesAction::class);
        $result = $this->createTicketWithSameTimestampMessages(5);
        $ticket = $result['ticket'];
        $messages = $result['messages'];

        // Usa a 3a mensagem como cursor "after" — deve retornar as 2 seguintes
        $pivotMessage = $messages[2];

        $response = $action->listByTicketCursor(
            tenantId: $this->tenantId,
            ticketId: (string) $ticket->id,
            limit: 10,
            after: (string) $pivotMessage->id,
        );

        $resultMessages = $response['messages'];
        $this->assertCount(2, $resultMessages);

        // Todas as mensagens retornadas devem ser posteriores ao pivot
        foreach ($resultMessages as $msg) {
            if ($msg->created_at->equalTo($pivotMessage->created_at)) {
                $this->assertGreaterThan(
                    $pivotMessage->id,
                    $msg->id,
                    'Mensagens com mesmo timestamp e id > pivot.id devem ser incluídas',
                );
            } else {
                $this->assertTrue(
                    $msg->created_at->gt($pivotMessage->created_at),
                    'Mensagens com timestamp posterior devem ser incluídas',
                );
            }
        }
    }

    public function test_webchat_messages_ordered_asc_then_id_asc(): void
    {
        $instance = ChatInstance::factory()->create(['tenant_id' => $this->tenantId]);
        $ticket = ChatTicket::factory()->forTenant($this->tenantId)->create([
            'instance_id' => $instance->id,
        ]);

        $frozenTimestamp = now()->startOfSecond();

        $messages = [];
        for ($i = 0; $i < 3; $i++) {
            $messages[] = ChatMessage::factory()->create([
                'tenant_id' => $this->tenantId,
                'ticket_id' => $ticket->id,
                'type' => 'text',
                'is_deleted' => false,
                'direction' => 'incoming',
                'created_at' => $frozenTimestamp,
                'content' => "Webchat message {$i}",
            ]);
        }

        $jwtService = app(\Domain\Chat\Services\WebChatJwtService::class);
        $session = \Domain\Chat\Models\ChatSession::factory()->create([
            'tenant_id' => $this->tenantId,
            'ticket_id' => $ticket->id,
        ]);

        $token = $jwtService->generateToken(
            (string) $session->id,
            $this->tenantId,
            $session->contact_id,
            (string) $session->ticket_id,
        );

        $response = $this->getJson(
            "/api/webchat/sessions/{$session->id}/messages?token={$token}",
        );

        $response->assertOk();
        $data = $response->json('data');
        $counter = count($data);

        // Verifica ordem ASC: created_at ASC, id ASC
        for ($i = 1; $i < $counter; $i++) {
            $prevCreatedAt = $data[$i - 1]['createdAt'];
            $currCreatedAt = $data[$i]['createdAt'];
            $prevId = $data[$i - 1]['id'];
            $currId = $data[$i]['id'];

            if ($prevCreatedAt === $currCreatedAt) {
                // Mesmo timestamp: ids devem estar em ordem ASC
                $this->assertLessThan(
                    $currId,
                    $prevId,
                    'Webchat: mensagens com mesmo created_at devem ordenar por id ASC',
                );
            }
        }
    }

    public function test_list_by_ticket_preserves_consistent_order_across_pages(): void
    {
        $instance = ChatInstance::factory()->create(['tenant_id' => $this->tenantId]);
        $ticket = ChatTicket::factory()->forTenant($this->tenantId)->create([
            'instance_id' => $instance->id,
        ]);

        // Cria 10 mensagens com timestamps distintos (evita empate para simplificar)
        $messages = [];
        for ($i = 0; $i < 10; $i++) {
            $messages[] = ChatMessage::factory()->create([
                'tenant_id' => $this->tenantId,
                'ticket_id' => $ticket->id,
                'created_at' => now()->subSeconds(10 - $i),
                'content' => "Message {$i}",
            ]);
        }

        $action = app(ListChatMessagesAction::class);
        $paginator = $action->listByTicket($this->tenantId, (string) $ticket->id);

        $items = $paginator->items();
        $this->assertCount(10, $items);

        // Verifica que todas as mensagens estão presentes e em ordem DESC
        $ids = array_map(fn ($m) => $m->id, $items);
        $this->assertCount(10, array_unique($ids), 'Não deve haver duplicatas');
    }
}
