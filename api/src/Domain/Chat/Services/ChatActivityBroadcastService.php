<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Carbon\Carbon;
use Domain\Shared\Contracts\ActivityBroadcastService;

/**
 * Serviço para emitir evento unificado chat.activity.
 *
 * Agrupa múltiplos subevents em um único evento WebSocket para:
 * - Reduzir número de conexões/emissões
 * - Garantir ordenação consistente de atualizações
 * - Simplificar lógica de atualização no frontend
 *
 * @category Services
 *
 * @example
 * $service = app(ChatActivityBroadcastService::class);
 * $service->emit('ticket-123', [
 *     ['type' => 'msg.received', 'data' => ['message_id' => 'abc', ...]],
 *     ['type' => 'chat.list.updated', 'data' => ['ticket_id' => 'ticket-123', ...]],
 * ]);
 */
final class ChatActivityBroadcastService implements ActivityBroadcastService
{
    private const MAX_ENVELOPE_BYTES = 16384;

    /**
     * @var list<string>
     */
    private const STRIP_KEYS = ['raw', 'JPEGThumbnail', 'base64', 'profilePicThumbObj'];

    /**
     * Tipos de subevents válidos.
     *
     * @var list<string>
     */
    public const VALID_SUBEVENT_TYPES = [
        'msg.received',
        'msg.status',
        'msg.reaction',
        'msg.edit',
        'msg.delete',
        'ai.processing.started',
        'ai.processing.completed',
        'ai.processing.failed',
        'ai.processing.rejected',
        'chat.list.updated',
        'contact.updated',
        'deal.updated',
        'negotiation.status.changed',
        'ticket.new',
        'ticket.updated',
        'typing',
    ];

    public function __construct(
        private readonly ChatBroadcastService $broadcastService
    ) {}

    /**
     * Emitir evento unificado chat.activity com múltiplos subevents.
     *
     * @param  string  $chatId  ID do ticket/chat relacionado
     * @param  array<array{type: string, data: array<string, mixed>}>  $subevents  Lista de subevents a agrupar
     * @param  string|null  $tenantId  ID do tenant (opcional, extrai do contexto se não informado)
     */
    public function emit(string $chatId, array $subevents, ?string $tenantId = null): void
    {
        if (empty($subevents)) {
            return;
        }

        // Validar e filtrar subevents
        $validSubevents = $this->filterValidSubevents($subevents);

        if (empty($validSubevents)) {
            logger()->warning('[ChatActivityBroadcast] No valid subevents to emit', [
                'chat_id' => $chatId,
                'original_count' => count($subevents),
            ]);

            return;
        }

        // Resolver tenant_id
        $resolvedTenantId = $tenantId ?? $this->resolveTenantId();

        $payload = [
            'event' => 'chat.activity',
            'chatId' => $chatId,
            'ticketId' => $chatId,
            'tenant_id' => $resolvedTenantId,
            'timestamp' => Carbon::now()->toIso8601String(),
            'subevents' => $this->compactSubevents($validSubevents),
        ];

        $payload = $this->ensureMaxPayloadSize($payload);

        logger()->info('[ChatActivityBroadcast] Emitting unified event', [
            'chat_id' => $chatId,
            'tenant_id' => $resolvedTenantId,
            'subevent_count' => count($validSubevents),
            'subevent_types' => array_column($validSubevents, 'type'),
        ]);

        // Emite para a sala do ticket e do tenant
        $this->broadcastService->emit(
            'chat.activity',
            $payload,
            "ticket:{$chatId}"
        );

        // Também emite para o tenant room para atualizações globais (lista de chats)
        if ($resolvedTenantId) {
            $this->broadcastService->emit(
                'chat.activity',
                $payload,
                "tenant:{$resolvedTenantId}"
            );
        }
    }

    /**
     * Emitir evento para nova mensagem recebida.
     *
     * Helper para o caso mais comum de uso.
     *
     * @param  string  $chatId  ID do ticket
     * @param  array<string, mixed>  $messageData  Dados da mensagem
     * @param  array<string, mixed>|null  $ticketData  Dados do ticket para atualizar lista (opcional)
     */
    public function emitMessageReceived(
        string $chatId,
        array $messageData,
        ?array $ticketData = null
    ): void {
        $tenantId = $messageData['tenant_id'] ?? null;

        $subevents = [
            [
                'type' => 'msg.received',
                'data' => $messageData,
            ],
        ];

        if ($ticketData !== null) {
            $subevents[] = [
                'type' => 'chat.list.updated',
                'data' => $ticketData,
            ];
        }

        $this->emit($chatId, $subevents, is_string($tenantId) ? $tenantId : null);
    }

    /**
     * Emitir evento para atualização de contato.
     *
     * @param  string  $chatId  ID do ticket relacionado
     * @param  string  $contactId  ID do contato atualizado
     * @param  array<string, mixed>  $contactData  Dados atualizados do contato
     */
    public function emitContactUpdated(
        string $chatId,
        string $contactId,
        array $contactData
    ): void {
        $this->emit($chatId, [
            [
                'type' => 'contact.updated',
                'data' => [
                    'contact_id' => $contactId,
                    'data' => $contactData,
                ],
            ],
        ]);
    }

    /**
     * Emitir evento para atualização de negociação.
     *
     * @param  string  $chatId  ID do ticket relacionado
     * @param  string  $dealId  ID da negociação atualizada
     * @param  array<string, mixed>  $dealData  Dados atualizados da negociação
     */
    public function emitDealUpdated(
        string $chatId,
        string $dealId,
        array $dealData
    ): void {
        $this->emit($chatId, [
            [
                'type' => 'deal.updated',
                'data' => [
                    'deal_id' => $dealId,
                    'data' => $dealData,
                ],
            ],
        ]);
    }

    /**
     * Emitir evento para atualização de status de mensagem.
     *
     * @param  string  $chatId  ID do ticket
     * @param  array<string, mixed>  $statusData  Dados do status (message_id, status, timestamps)
     */
    public function emitMessageStatus(string $chatId, array $statusData): void
    {
        $this->emit($chatId, [
            [
                'type' => 'msg.status',
                'data' => $statusData,
            ],
        ], isset($statusData['tenant_id']) ? (string) $statusData['tenant_id'] : null);
    }

    /**
     * Emitir evento para reação em mensagem.
     *
     * @param  string  $chatId  ID do ticket
     * @param  array<string, mixed>  $reactionData  Dados da reação
     */
    public function emitMessageReaction(string $chatId, array $reactionData): void
    {
        $this->emit($chatId, [
            [
                'type' => 'msg.reaction',
                'data' => $reactionData,
            ],
        ]);
    }

    /**
     * Emitir evento para edição de mensagem.
     *
     * @param  string  $chatId  ID do ticket
     * @param  array<string, mixed>  $editData  Dados da edição
     */
    public function emitMessageEdit(string $chatId, array $editData): void
    {
        $this->emit($chatId, [
            [
                'type' => 'msg.edit',
                'data' => $editData,
            ],
        ]);
    }

    /**
     * Emitir evento para exclusão de mensagem.
     *
     * @param  string  $chatId  ID do ticket
     * @param  array<string, mixed>  $deleteData  Dados da exclusão
     */
    public function emitMessageDelete(string $chatId, array $deleteData): void
    {
        $this->emit($chatId, [
            [
                'type' => 'msg.delete',
                'data' => $deleteData,
            ],
        ]);
    }

    /**
     * Emitir evento para mudança de status de negociação.
     *
     * @param  string  $tenantId  ID do tenant
     * @param  string  $contactId  ID do contato relacionado
     * @param  array<string, mixed>  $statusData  Dados da mudança de status
     */
    public function broadcastNegotiationStatusChanged(string $tenantId, string $contactId, array $statusData): void
    {
        // Buscar tickets do contato para emitir nas salas corretas
        $tickets = \Domain\Chat\Models\ChatTicket::query()
            ->where('tenant_id', $tenantId)
            ->where('crm_contact_id', $contactId)
            ->pluck('id');

        foreach ($tickets as $ticketId) {
            $this->emit((string) $ticketId, [
                [
                    'type' => 'negotiation.status.changed',
                    'data' => $statusData,
                ],
            ], $tenantId);
        }
    }

    /**
     * Filtrar subevents válidos.
     *
     * @param  array<int, array<string, mixed>>  $subevents
     * @return array<array{type: string, data: mixed}>
     */
    private function filterValidSubevents(array $subevents): array
    {
        /** @var array<array{type: string, data: mixed}> $result */
        $result = array_values(array_filter($subevents, function (array $subevent): bool {
            if (! isset($subevent['type']) || ! is_string($subevent['type'])) {
                return false;
            }

            if (! array_key_exists('data', $subevent)) {
                return false;
            }

            return in_array($subevent['type'], self::VALID_SUBEVENT_TYPES, true);
        }));

        return $result;
    }

    /**
     * Compacta subevents removendo dados pesados e campos redundantes.
     *
     * @param  array<array{type: string, data: mixed}>  $subevents
     * @return array<array{type: string, data: mixed}>
     */
    private function compactSubevents(array $subevents): array
    {
        return array_map(function (array $subevent): array {
            $data = $subevent['data'];

            if (! is_array($data)) {
                return [
                    'type' => $subevent['type'],
                    'data' => $data,
                ];
            }

            return [
                'type' => $subevent['type'],
                'data' => $this->sanitizePayloadArray($data),
            ];
        }, $subevents);
    }

    /**
     * Garante envelope <= 4KB; se exceder, mantém apenas deltas mínimos por subevent.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function ensureMaxPayloadSize(array $payload): array
    {
        $encoded = json_encode($payload);

        if (is_string($encoded) && strlen($encoded) <= self::MAX_ENVELOPE_BYTES) {
            return $payload;
        }

        $subevents = $payload['subevents'] ?? [];
        if (! is_array($subevents)) {
            return $payload;
        }

        $payload['subevents'] = array_map(function (mixed $subevent): array {
            if (! is_array($subevent)) {
                return [
                    'type' => 'chat.list.updated',
                    'data' => [],
                ];
            }

            $type = isset($subevent['type']) && is_string($subevent['type'])
                ? $subevent['type']
                : 'chat.list.updated';

            $data = isset($subevent['data']) && is_array($subevent['data'])
                ? $subevent['data']
                : [];

            return [
                'type' => $type,
                'data' => $this->extractDeltaPayload($data),
            ];
        }, $subevents);

        return $payload;
    }

    /**
     * Remove recursivamente campos grandes e normaliza payload para tempo real.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function sanitizePayloadArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array($key, self::STRIP_KEYS, true)) {
                unset($data[$key]);

                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->sanitizePayloadArray($value);
            }
        }

        return $data;
    }

    /**
     * Extrai payload mínimo para manter atualização incremental no frontend.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function extractDeltaPayload(array $data): array
    {
        $delta = [];

        foreach (['id', 'message_id', 'ticket_id', 'tenant_id', 'status', 'created_at', 'updated_at'] as $key) {
            if (array_key_exists($key, $data)) {
                $delta[$key] = $data[$key];
            }
        }

        if (isset($data['message']) && is_array($data['message'])) {
            $delta['message'] = [];

            foreach (['id', 'content', 'type', 'direction', 'status', 'created_at'] as $messageKey) {
                if (array_key_exists($messageKey, $data['message'])) {
                    $delta['message'][$messageKey] = $data['message'][$messageKey];
                }
            }

            // Preserve contact/location metadata for proper UI rendering of special message types.
            if (isset($data['message']['metadata']) && is_array($data['message']['metadata'])) {
                $compactMeta = [];
                foreach (['contact', 'location'] as $metaKey) {
                    if (isset($data['message']['metadata'][$metaKey])) {
                        $compactMeta[$metaKey] = $data['message']['metadata'][$metaKey];
                    }
                }
                if (! empty($compactMeta)) {
                    $delta['message']['metadata'] = $compactMeta;
                }
            }
        }

        return $delta;
    }

    /**
     * Resolver tenant_id do contexto atual.
     */
    private function resolveTenantId(): ?string
    {
        // Tenta obter do usuário autenticado
        $user = auth()->user();
        if ($user && isset($user->tenant_id)) {
            return (string) $user->tenant_id;
        }

        // Tenta obter do contexto de tenant (se usar multi-tenancy package)
        if (function_exists('tenant') && tenant()) {
            return (string) tenant()->id;
        }

        return null;
    }
}
