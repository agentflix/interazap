<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Jobs\SyncReadReceiptJob;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatSession;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatActivityBroadcastService;
use Domain\Chat\Services\ChatGatewayService;
use Domain\Chat\Services\WebChatRedisPublisher;
use Domain\Configuration\Events\TicketAssignedEvent;
use Domain\Configuration\Events\TicketClosedEvent;
use Domain\Platform\Models\PlatformTenant;
use Domain\Platform\Services\PlatformPlanEnforcementService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Atualizar status, leitura e abertura de tickets. */
final class UpdateChatTicketAction
{
    public function __construct(
        private readonly ChatGatewayService $gateway,
        private readonly ChatActivityBroadcastService $activityBroadcast,
        private readonly SendTicketMessageAction $messageAction,
        private readonly EvaluateTicketCsatAction $evaluateAction,
        private readonly WebChatRedisPublisher $webChatPublisher,
        private readonly PlatformPlanEnforcementService $planEnforcement,
    ) {}

    /**
     * Atualizar status do ticket com encerramento condicional.
     *
     * @param  string  $mode  'normal' ou 'forced'
     */
    public function updateStatus(
        ChatTicket $ticket,
        string $status,
        ?string $reason = null,
        string $mode = 'normal',
        ?string $closedByUserId = null,
    ): ChatTicket {
        $isForced = $mode === 'forced';
        $ticket->status = $status;

        if ($status === 'closed') {
            $ticket->closed_at = now();
            $ticket->loadMissing('extended');
            $ticket->close_reason = $reason;
            $ticket->closed_mode = $mode;
            $ticket->current_ai_agent_id = null;
            if ($closedByUserId) {
                $ticket->closed_by = $closedByUserId;
            }

            if (! $isForced) {
                $this->evaluateAction->evaluate($ticket);
            }
        } elseif (in_array($status, ['open', 'in_progress'], true)) {
            if (! $ticket->started_at) {
                $ticket->started_at = now();
            }
        }

        $ticket->save();
        Cache::forget("chat_counts:{$ticket->tenant_id}");
        if ($status === 'closed' && ! $isForced) {
            if ($mode === 'auto_inactivity') {
                $this->sendAutoCloseMessage($ticket);
            } else {
                $this->messageAction->sendConfiguredSystemMessage(
                    $ticket,
                    'send_end_service_message',
                    'end_service_message',
                    'end_service_message'
                );
            }
        }
        if ($status === 'closed') {
            $this->dispatchFinalSentimentAnalysis($ticket);
            TicketClosedEvent::dispatch(
                (string) $ticket->tenant_id,
                (string) $ticket->id,
                $ticket->assigned_to ? (string) $ticket->assigned_to : null,
                $ticket->closed_mode ?? null,
            );

            $ticket->load(['latestMessage', 'contact', 'user']);
            $this->activityBroadcast->emit(
                (string) $ticket->id,
                [
                    [
                        'type' => 'ticket.updated',
                        'data' => [
                            'ticket_id' => (string) $ticket->id,
                            'tenant_id' => (string) $ticket->tenant_id,
                            'ticket' => $ticket->toArray(),
                            'event_type' => 'ticket_closed',
                        ],
                    ],
                ],
                (string) $ticket->tenant_id
            );

            // Notificar cliente webchat se o ticket veio de uma sessão webchat
            $webchatSession = ChatSession::query()
                ->where('ticket_id', (string) $ticket->id)
                ->where('tenant_id', (string) $ticket->tenant_id)
                ->first();

            if ($webchatSession !== null) {
                $this->webChatPublisher->publishTicketClosed(
                    (string) $webchatSession->id,
                    (string) $ticket->tenant_id,
                    (string) $ticket->id,
                );
            }
        }

        return $ticket;
    }

    /**
     * Marcar ticket como lido localmente e sincronizar com gateway.
     */
    public function markAsRead(string $tenantId, string $id, ?ChatTicket $ticket = null): void
    {
        $ticket ??= $this->findForRead($tenantId, $id);
        $now = now();
        ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $id)
            ->where('direction', 'incoming')
            ->whereNull('read_at')
            ->update(['read_at' => $now]);
        $instance = $ticket->instance;
        $token = $instance ? $this->resolveGatewayToken($instance) : null;
        $number = $this->resolveContactPhone($ticket);
        if (! $token || ! $number) {
            return;
        }
        $cleaned = (string) preg_replace('/[^0-9]/', '', $number);
        if ($cleaned === '') {
            return;
        }
        SyncReadReceiptJob::dispatch($token, $cleaned);
    }

    /**
     * Abrir ticket assumindo o atendimento.
     *
     *
     * @throws \DomainException
     */
    public function open(string $tenantId, string $id, string $userId): ChatTicket
    {
        $ticket = $this->find($tenantId, $id);

        if (in_array($ticket->status, ['open', 'in_progress'], true)) {
            throw new \DomainException('Este chamado já está em atendimento.');
        }
        if ($ticket->status === 'closed') {
            throw new \DomainException('Este chamado já foi encerrado e não pode ser reaberto.');
        }
        $ticket->status = 'in_progress';
        $ticket->assigned_to = $userId;
        $ticket->started_at = now();
        // Iniciar atendimento ativa takeover humano: encerra o fluxo de IA neste ticket.
        $ticket->loadMissing('extended');
        if (! $ticket->human_takeover_at) {
            $ticket->human_takeover_at = now();
        }
        $ticket->save();
        $ticket->flushPendingExtended();
        Cache::forget("chat_counts:{$tenantId}");
        TicketAssignedEvent::dispatch((string) $tenantId, (string) $ticket->id, $userId);
        $this->messageAction->sendConfiguredSystemMessage(
            $ticket,
            'send_start_service_message',
            'start_service_message',
            'start_service_message'
        );
        $ticket->load(['latestMessage', 'contact', 'user']);
        $this->activityBroadcast->emit(
            (string) $ticket->id,
            [
                [
                    'type' => 'ticket.updated',
                    'data' => [
                        'ticket_id' => (string) $ticket->id,
                        'tenant_id' => (string) $ticket->tenant_id,
                        'ticket' => $ticket->toArray(),
                        'event_type' => 'ticket_opened',
                    ],
                ],
            ],
            (string) $ticket->tenant_id
        );

        return $ticket;
    }

    /**
     * Localizar ticket pelo ID com eager loading completo.
     */
    public function find(string $tenantId, string $id): ChatTicket
    {
        return ChatTicket::query()
            ->with([
                'extended',
                'evaluation:id,ticket_id,rating,comment,submitted_at',
                'instance:id,name,provider',
                'contact:id,name,phone,whatsapp,email,avatar_url,tenant_id',
                'user:id,name',
                'latestMessage' => fn ($relation) => $relation->with(['user:id,name', 'extended']),
            ])
            ->withCount([
                'messages as unread_count' => fn ($messageQuery) => $messageQuery
                    ->where('direction', 'incoming')
                    ->whereNull('read_at'),
            ])
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }

    /**
     * Localizar ticket mínimo para operações de leitura.
     */
    public function findForRead(string $tenantId, string $id): ChatTicket
    {
        return ChatTicket::query()
            ->select(['id', 'tenant_id', 'instance_id', 'contact_id', 'phone_e164', 'phone', 'remote_jid'])
            ->with(['instance:id,webhook_token,settings_json', 'contact:id,whatsapp,phone'])
            ->where('tenant_id', $tenantId)
            ->findOrFail($id);
    }

    private function dispatchFinalSentimentAnalysis(ChatTicket $ticket): void
    {
        if (! $this->planEnforcement->isAiEnabled((string) $ticket->tenant_id)) {
            return;
        }

        $lastInboundText = ChatMessage::query()
            ->where('tenant_id', $ticket->tenant_id)
            ->where('ticket_id', $ticket->id)
            ->where('direction', 'incoming')
            ->where('type', 'text')
            ->whereNotNull('content')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->value('content');
        if (! is_string($lastInboundText) || trim($lastInboundText) === '') {
            return;
        }
        \Domain\Ai\Jobs\AiAnalyzeSentimentJob::dispatch(
            (string) $ticket->id,
            $lastInboundText,
            (string) $ticket->tenant_id,
        )->onQueue('sentiment');
    }

    private function resolveContactPhone(ChatTicket $ticket): ?string
    {
        $number = $ticket->phone_e164 ?? $ticket->phone ?? $ticket->remote_jid;
        if (! $number && $ticket->contact) {
            $number = $ticket->contact->whatsapp ?? $ticket->contact->phone;
        }
        if (! is_string($number) || $number === '') {
            return null;
        }

        return str_contains($number, '@') ? Str::before($number, '@') : $number;
    }

    /** @param  \Domain\Chat\Models\ChatInstance  $instance */
    private function resolveGatewayToken($instance): ?string
    {
        $settingsToken = is_array($instance->settings_json) ? $instance->settings_json['token'] ?? null : null;
        if (is_string($settingsToken) && $settingsToken !== '') {
            return $settingsToken;
        }

        return $instance->webhook_token !== '' ? $instance->webhook_token : null;
    }

    /**
     * Envia mensagem de auto-fechamento por inatividade, substituindo
     * a end_service_message padrão para evitar spam duplicado.
     */
    private function sendAutoCloseMessage(ChatTicket $ticket): void
    {
        if (! $ticket->instance_id) {
            return;
        }

        $instance = ChatInstance::query()
            ->select(['id', 'tenant_id', 'auto_close_enabled', 'auto_close_after_minutes', 'auto_close_target', 'auto_close_message', 'provider', 'webhook_token', 'settings_json'])
            ->where('tenant_id', $ticket->tenant_id)
            ->find($ticket->instance_id);

        if (! $instance) {
            return;
        }

        $tenant = PlatformTenant::query()->find($ticket->tenant_id);

        if (! $tenant) {
            return;
        }

        $config = $instance->getEffectiveAutoCloseConfig($tenant);
        $message = trim((string) ($config['message'] ?? ''));

        if ($message === '') {
            return;
        }

        // Enviar diretamente, sem depender de settings_json
        $phone = $this->resolveContactPhone($ticket);
        $token = $this->resolveGatewayToken($instance);

        if (! $phone || ! $token) {
            return;
        }

        $chatMessage = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            'tenant_id' => (string) $ticket->tenant_id,
            'ticket_id' => (string) $ticket->id,
            'user_id' => $ticket->assigned_to,
            'contact_id' => $ticket->contact_id,
            'content' => $message,
            'type' => 'text',
            'direction' => 'outgoing',
            'is_from_contact' => false,
            'source' => 'system',
            'status' => 'pending',
            'metadata' => [
                'kind' => 'auto_close_inactivity',
            ],
        ]);

        try {
            if ($instance->provider === 'zapi') {
                $response = $this->gateway->sendOutboundMessage(
                    'zapi',
                    (string) $token,
                    (string) $ticket->tenant_id,
                    (string) $instance->id,
                    [
                        'type' => 'text',
                        'to' => $phone,
                        'text' => $message,
                    ]
                );
            } else {
                $response = $this->gateway->sendText((string) $token, [
                    'number' => $phone,
                    'text' => $message,
                ]);
            }

            $chatMessage->status = 'sent';
            $chatMessage->sent_at = now();
            $chatMessage->external_id = $response['messageid'] ?? $response['id'] ?? null;
            $chatMessage->metadata = array_merge($chatMessage->metadata ?? [], ['gateway_response' => $response]);
            $chatMessage->save();
        } catch (\Throwable $exception) {
            $chatMessage->status = 'failed';
            $chatMessage->error_message = $exception->getMessage();
            $chatMessage->save();

            \Illuminate\Support\Facades\Log::warning('chat.auto_close_message_send_failed', [
                'tenant_id' => (string) $ticket->tenant_id,
                'ticket_id' => (string) $ticket->id,
                'instance_id' => (string) $instance->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
