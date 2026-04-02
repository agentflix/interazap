<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Shared\Services\GatewayBroadcastService;

/**
 * Serviço de Broadcast WebSocket.
 *
 * Facade responsável pela distribuição de eventos em tempo real
 * utilizando Socket.io via Gateway NestJS.
 *
 * Os payloads são automaticamente sanitizados para remover campos
 * pesados (JPEGThumbnail, raw, base64) que excedem limites de WebSocket.
 *
 * @category Services
 */
final class ChatBroadcastService
{
    /**
     * Limite aproximado de tamanho do payload JSON (~8KB para margem de segurança).
     */
    private const MAX_PAYLOAD_SIZE = 8192;

    /**
     * Chaves a remover recursivamente do payload (campos pesados).
     *
     * @var list<string>
     */
    private const STRIP_KEYS = [
        'raw',
        'RAW',
        'JPEGThumbnail',
        'jpegThumbnail',
        'thumbnail',
        'base64',
        'mediaData',
        'fileContent',
        'binaryData',
    ];

    public function __construct(
        private readonly GatewayBroadcastService $gatewayBroadcast
    ) {}

    /**
     * Emitir evento de atualização de status de mensagem (sent, delivered, read).
     *
     * @param array{
     *   message_id: string,
     *   ticket_id: string,
     *   tenant_id: string,
     *   status: string,
     *   error_message?: string|null,
     *   sent_at?: string|null,
     *   delivered_at?: string|null,
     *   read_at?: string|null
     * } $payload Dados do status da mensagem.
     */
    public function emitMessageStatus(array $payload): void
    {
        try {
            $safePayload = $this->sanitizePayload($payload);
            $this->gatewayBroadcast->broadcastMessageStatus($safePayload);
        } catch (\Throwable $e) {
            logger()->warning('[ChatBroadcastService] Failed to emit message status', [
                'error' => $e->getMessage(),
                'payload_keys' => array_keys($payload),
            ]);
        }
    }

    /**
     * Notificar clientes sobre o recebimento de uma nova mensagem.
     *
     * @param array{
     *   ticket_id: string,
     *   tenant_id: string,
     *   message: array<string, mixed>
     * } $payload Estrutura completa da mensagem recebida.
     */
    public function emitNewMessage(array $payload): void
    {
        try {
            $safePayload = $this->sanitizePayload($payload);
            $this->gatewayBroadcast->broadcastNewMessage($safePayload);
        } catch (\Throwable $e) {
            logger()->warning('[ChatBroadcastService] Failed to emit new message', [
                'error' => $e->getMessage(),
                'payload_keys' => array_keys($payload),
            ]);
        }
    }

    /**
     * Notificar clientes sobre a criação de um novo ticket.
     *
     * @param array{
     *   ticket_id: string,
     *   tenant_id: string,
     *   ticket: array<string, mixed>
     * } $payload Estrutura completa do ticket criado.
     */
    public function emitNewTicket(array $payload): void
    {
        try {
            logger()->info('[ChatBroadcastService] Emitting new ticket', [
                'ticket_id' => $payload['ticket_id'],
                'tenant_id' => $payload['tenant_id'],
            ]);

            $safePayload = $this->sanitizePayload($payload);

            $this->gatewayBroadcast->broadcastEvent(
                'chat.ticket.new',
                $safePayload,
                null
            );
        } catch (\Throwable $e) {
            logger()->warning('[ChatBroadcastService] Failed to emit new ticket', [
                'error' => $e->getMessage(),
                'payload_keys' => array_keys($payload),
            ]);
        }
    }

    /**
     * Notificar clientes sobre atualização em um ticket (nova mensagem, status, etc).
     *
     * @param array{
     *   ticket_id: string,
     *   tenant_id: string,
     *   ticket: array<string, mixed>
     * } $payload Dados do ticket atualizado.
     */
    public function emitTicketUpdated(array $payload): void
    {
        try {
            $safePayload = $this->sanitizePayload($payload);

            $this->gatewayBroadcast->broadcastEvent(
                'chat.ticket.updated',
                $safePayload,
                null
            );
        } catch (\Throwable $e) {
            logger()->warning('[ChatBroadcastService] Failed to emit ticket updated', [
                'error' => $e->getMessage(),
                'payload_keys' => array_keys($payload),
            ]);
        }
    }

    /**
     * Disparar um evento genérico para uma sala específica.
     *
     * Método flexível para casos de uso personalizados que não se encaixam
     * nos fluxos padrão de mensagem.
     *
     * @param  string  $event  Nome do evento (ex: 'ticket.updated').
     * @param  array<string, mixed>  $data  Payload de dados do evento.
     * @param  string|null  $room  Sala de destino (opcional, ex: 'ticket:{id}').
     */
    public function emit(string $event, array $data, ?string $room = null): void
    {
        try {
            $safeData = $this->sanitizePayload($data);

            logger()->info('[ChatBroadcastService] Emitting event', [
                'event' => $event,
                'room' => $room,
                'data_keys' => array_keys($safeData),
            ]);

            $this->gatewayBroadcast->broadcastEvent($event, $safeData, $room);
        } catch (\Throwable $e) {
            logger()->warning('[ChatBroadcastService] Failed to emit event', [
                'error' => $e->getMessage(),
                'event' => $event,
            ]);
        }
    }

    /**
     * Emitir evento de reação a mensagem.
     *
     * @param array{
     *   message_id: string,
     *   ticket_id: string,
     *   tenant_id: string,
     *   emoji: string|null,
     *   from_me?: bool,
     *   reactions?: array<array{emoji: string, from_me: bool, timestamp: string}>
     * } $payload Dados da reação.
     */
    public function emitMessageReaction(array $payload): void
    {
        try {
            $safePayload = $this->sanitizePayload($payload);

            $this->gatewayBroadcast->broadcastEvent(
                'chat.message.reaction',
                $safePayload,
                null
            );
        } catch (\Throwable $e) {
            logger()->warning('[ChatBroadcastService] Failed to emit message reaction', [
                'error' => $e->getMessage(),
                'payload_keys' => array_keys($payload),
            ]);
        }
    }

    /**
     * Emitir evento de edição de mensagem.
     *
     * @param array{
     *   message_id: string,
     *   ticket_id: string,
     *   tenant_id: string,
     *   content: string,
     *   is_edited: bool,
     *   edited_at?: string|null
     * } $payload Dados da edição.
     */
    public function emitMessageEdit(array $payload): void
    {
        try {
            $safePayload = $this->sanitizePayload($payload);

            $this->gatewayBroadcast->broadcastEvent(
                'chat.message.edit',
                $safePayload,
                null
            );
        } catch (\Throwable $e) {
            logger()->warning('[ChatBroadcastService] Failed to emit message edit', [
                'error' => $e->getMessage(),
                'payload_keys' => array_keys($payload),
            ]);
        }
    }

    /**
     * Emitir evento de exclusão de mensagem.
     *
     * @param array{
     *   message_id: string,
     *   ticket_id: string,
     *   tenant_id: string,
     *   status?: string,
     *   deleted_at?: string|null,
     *   deleted_by?: string|null
     * } $payload Dados da exclusão.
     */
    public function emitMessageDelete(array $payload): void
    {
        try {
            $safePayload = $this->sanitizePayload($payload);

            $this->gatewayBroadcast->broadcastEvent(
                'chat.message.delete',
                $safePayload,
                null
            );
        } catch (\Throwable $e) {
            logger()->warning('[ChatBroadcastService] Failed to emit message delete', [
                'error' => $e->getMessage(),
                'payload_keys' => array_keys($payload),
            ]);
        }
    }

    /**
     * Emitir evento de digitação para a sala do tenant.
     *
     * @param  string  $tenantId  ID do tenant.
     * @param  string  $ticketId  ID do ticket.
     * @param  bool  $isTyping  true = digitando, false = parou.
     * @param  string|null  $presence  'composing' | 'recording' | 'paused' | null.
     */
    public function emitTyping(string $tenantId, string $ticketId, bool $isTyping, ?string $presence = null): void
    {
        try {
            $this->gatewayBroadcast->broadcastEvent(
                'chat.typing',
                [
                    'tenant_id' => $tenantId,
                    'ticket_id' => $ticketId,
                    'is_typing' => $isTyping,
                    'presence' => $presence,
                ],
                "tenant:{$tenantId}"
            );
        } catch (\Throwable $e) {
            logger()->warning('[ChatBroadcastService] Failed to emit typing', [
                'error' => $e->getMessage(),
                'tenant_id' => $tenantId,
                'ticket_id' => $ticketId,
            ]);
        }
    }

    /**
     * Sanitiza payload para broadcast, removendo campos pesados.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function sanitizePayload(array $payload): array
    {
        // Primeiro, remover chaves proibidas recursivamente
        $sanitized = $this->stripKeysRecursive($payload, self::STRIP_KEYS);

        // Verificar tamanho estimado do JSON
        $jsonSize = strlen((string) json_encode($sanitized));

        if ($jsonSize > self::MAX_PAYLOAD_SIZE) {
            logger()->warning('[ChatBroadcastService] Payload still too large after sanitization', [
                'original_size' => strlen((string) json_encode($payload)),
                'sanitized_size' => $jsonSize,
                'max_size' => self::MAX_PAYLOAD_SIZE,
            ]);

            // Truncar metadata se ainda estiver muito grande
            $sanitized = $this->truncateMetadata($sanitized);
        }

        return $sanitized;
    }

    /**
     * Remove chaves específicas de um array de forma recursiva.
     *
     * @param  array<string, mixed>  $data
     * @param  list<string>  $keys
     * @return array<string, mixed>
     */
    private function stripKeysRecursive(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            unset($data[$key]);
        }

        foreach ($data as $k => $value) {
            if (is_array($value)) {
                $data[$k] = $this->stripKeysRecursive($value, $keys);
            }
        }

        return $data;
    }

    /**
     * Trunca campos de metadata se o payload ainda estiver muito grande.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function truncateMetadata(array $payload): array
    {
        // Se houver 'message' com 'metadata', simplificar
        if (isset($payload['message']['metadata']) && is_array($payload['message']['metadata'])) {
            $payload['message']['metadata'] = $this->simplifyMetadata($payload['message']['metadata']);
        }

        // Se houver 'ticket' com 'latest_message', simplificar
        if (isset($payload['ticket']['latest_message']['metadata']) && is_array($payload['ticket']['latest_message']['metadata'])) {
            $payload['ticket']['latest_message']['metadata'] = $this->simplifyMetadata($payload['ticket']['latest_message']['metadata']);
        }

        // Se houver 'metadata' no root
        if (isset($payload['metadata']) && is_array($payload['metadata'])) {
            $payload['metadata'] = $this->simplifyMetadata($payload['metadata']);
        }

        return $payload;
    }

    /**
     * Simplifica o metadata mantendo apenas campos essenciais.
     *
     * @param  array<string, mixed>  $metadata
     * @return array<string, mixed>
     */
    private function simplifyMetadata(array $metadata): array
    {
        // Manter campos essenciais usados pelo frontend de chat.
        // Importante: preservar contact/location para render de cards em realtime.
        $essential = [
            'messageId',
            'messageid',
            'type',
            'sender',
            'isFromMe',
            'timestamp',
            'status',
            'content',
            'quotedMessage',
            'quoted_message_id',
            'provider',
            'event_type',
            'direction',
            'instance_id',
            'instance_webhook_token',
            'message',
            'contact',
            'location',
        ];

        $simplified = [];
        foreach ($essential as $key) {
            if (isset($metadata[$key])) {
                $value = $metadata[$key];

                // Para content, manter apenas URL, não base64 ou binários
                if ($key === 'content' && is_array($value)) {
                    $value = $this->stripKeysRecursive($value, self::STRIP_KEYS);
                }

                // Para message, preservar apenas campos compactos necessários para UI.
                if ($key === 'message' && is_array($value)) {
                    $value = [
                        'id' => $value['id'] ?? null,
                        'type' => $value['type'] ?? null,
                        'chatid' => $value['chatid'] ?? null,
                        'fromMe' => $value['fromMe'] ?? null,
                        'timestamp' => $value['timestamp'] ?? null,
                        'body' => $value['body'] ?? null,
                        'text' => $value['text'] ?? null,
                    ];
                    $value = array_filter($value, static fn ($item): bool => $item !== null);
                }

                $simplified[$key] = $value;
            }
        }

        return $simplified;
    }
}
