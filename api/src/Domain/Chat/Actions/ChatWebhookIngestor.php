<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\Actions\WebhookHandlers\ChatWebhookDeleteHandler;
use Domain\Chat\Actions\WebhookHandlers\ChatWebhookEditHandler;
use Domain\Chat\Actions\WebhookHandlers\ChatWebhookReactionHandler;
use Domain\Chat\DTOs\ChatMessageDTO;
use Domain\Chat\DTOs\ChatTicketDTO;
use Domain\Chat\Events\MessagePersisted;
use Domain\Chat\Events\MetaTemplateStatusUpdated;
use Domain\Chat\Jobs\ChatMediaDownloadJob;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatBroadcastService;
use Domain\Chat\Services\MetaWindowService;
use Domain\Configuration\Models\ConfigurationOpeningHour;
use Domain\Platform\Models\PlatformUazapiInstance;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Serviço de Ingestão de Dados de Webhooks.
 *
 * Responsável por interpretar payloads normalizados vindos dos gateways,
 * gerenciar o ciclo de vida de tickets (criação/atualização), salvar novas mensagens,
 * atualizar status de mensagens existentes e executar automações (auto reply).
 *
 * @category Actions
 */
final class ChatWebhookIngestor
{
    /**
     * @param  ChatTicketActions  $ticketActions  Casos de uso de tickets.
     * @param  SendChatMessageAction  $messageActions  Action de envio de mensagens.
     * @param  ChatBroadcastService  $broadcastService  Serviço de mensageria em tempo real.
     * @param  \Domain\Chat\Services\MetaWindowService  $metaWindowService  Serviço da janela de atendimento Meta (24h/72h CTWA).
     */
    public function __construct(
        private readonly ChatTicketActions $ticketActions,
        private readonly SendChatMessageAction $messageActions,
        private readonly ChatBroadcastService $broadcastService,
        private readonly MetaWindowService $metaWindowService,
        ChatWebhookReactionHandler $reactionHandler,
        ChatWebhookDeleteHandler $deleteHandler,
        ChatWebhookEditHandler $editHandler,
    ) {
        $this->handlers = [
            $reactionHandler,
            $deleteHandler,
            $editHandler,
        ];
    }

    /**
     * @var array<int, \Domain\Chat\Actions\WebhookHandlers\ChatWebhookHandlerInterface>
     */
    private array $handlers = [];

    /**
     * Processa um payload normalizado para ingestão no banco de dados.
     *
     * Identifica o tipo de evento, resolve tickets, salva mensagens e
     * dispara gatilhos de automação se necessário.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<string, mixed>  $payload  Dados normalizados do evento.
     */
    public function ingest(string $tenantId, array $payload): void
    {
        $eventType = strtolower((string) ($payload['event_type'] ?? ''));
        logger()->debug('[ChatWebhookIngestor] Processing', [
            'event_type' => $eventType,
            'tenant_id' => $tenantId,
            'payload_keys' => array_keys($payload),
        ]);

        // Eventos de status de template Meta vêm pelo stream chat-events
        // (gateway publica após webhook `message_template_status_update`).
        // Direction normalizada pelo gateway = 'template_status'.
        $direction = isset($payload['direction']) && is_string($payload['direction'])
            ? strtolower($payload['direction'])
            : null;

        if ($eventType === 'meta.template.status_updated' || $direction === 'template_status') {
            $this->dispatchMetaTemplateStatusUpdated($tenantId, $payload);

            return;
        }

        // Detectar eventos de conexão (connection, connection.update, etc.)
        $isConnectionEvent = str_starts_with($eventType, 'connection');
        if ($isConnectionEvent) {
            logger()->debug('[ChatWebhookIngestor] Connection event detected, updating instance status');
            $this->updateInstanceConnectionStatus($tenantId, $payload);
        }

        foreach ($this->handlers as $handler) {
            if ($handler->supports($eventType, $payload)) {
                $handler->handle($tenantId, $payload);

                return;
            }
        }

        $message = $payload['message'] ?? null;

        /**
         * Tratamento PRIORITÁRIO de events messages_update.
         *
         * Estes eventos NÃO possuem payload['message'] — o status (Read/Delivered)
         * vem em payload['raw']['event']['Type'] com os IDs das mensagens em
         * payload['raw']['event']['MessageIDs'].
         *
         * Este handler deve vir ANTES do if (!$message) para garantir que
         * events messages_update sejam processados mesmo quando não há message object.
         */
        if ($eventType === 'messages_update') {
            // Tentar payload.raw.event (UazAPI) E payload.raw.raw.event (stream aninhado)
            $rawEvent = is_array($payload['raw']['event'] ?? null) ? $payload['raw']['event'] : [];
            if ($rawEvent === [] && is_array($payload['raw']['raw']['event'] ?? null)) {
                $rawEvent = $payload['raw']['raw']['event'];
            }
            $messageIds = is_array($rawEvent['MessageIDs'] ?? null) ? $rawEvent['MessageIDs'] : [];
            $typeValue = $rawEvent['Type'] ?? $rawEvent['type'] ?? null;

            logger()->debug('[ChatWebhookIngestor] messages_update event', [
                'type_value' => $typeValue,
                'message_ids_count' => count($messageIds),
                'has_message' => $message !== null,
                'raw_keys' => array_keys($payload['raw'] ?? []),
            ]);

            if (count($messageIds) > 0 && $typeValue !== null) {
                $updatedAny = false;
                $instanceIdForStatus = $this->stringOrNull($payload['instance_id'] ?? null);
                foreach ($messageIds as $msgId) {
                    if (is_string($msgId) || is_numeric($msgId)) {
                        $statusPayload = ['id' => (string) $msgId, 'status' => $typeValue];
                        if ($this->updateExistingMessageStatus($tenantId, (string) $msgId, $statusPayload, $instanceIdForStatus)) {
                            $updatedAny = true;
                        }
                    }
                }
                if ($updatedAny) {
                    return;
                }
            }

            // Se tem message object, usar seu id como fallback
            if ($message !== null) {
                $instanceIdForStatus = $this->stringOrNull($payload['instance_id'] ?? null);
                $externalId = $message['id'] ?? $message['messageid'] ?? null;
                if ($externalId && $this->updateExistingMessageStatus($tenantId, (string) $externalId, $message, $instanceIdForStatus)) {
                    return;
                }

                if ($externalId && $typeValue !== null) {
                    $statusPayload = ['id' => $externalId, 'status' => $typeValue];
                    if ($this->updateExistingMessageStatus($tenantId, (string) $externalId, $statusPayload, $instanceIdForStatus)) {
                        return;
                    }
                }
            }

            logger()->debug('[ChatWebhookIngestor] messages_update sem IDs válidos', [
                'message_ids_count' => count($messageIds),
            ]);

            return;
        }

        if (! $message) {
            $statusPayload = is_array($payload['status'] ?? null) ? $payload['status'] : null;
            $statusMessageId = is_array($statusPayload)
                ? ($statusPayload['messageId'] ?? $statusPayload['message_id'] ?? null)
                : null;
            $statusValue = is_array($statusPayload)
                ? ($statusPayload['status'] ?? null)
                : null;

            logger()->debug('[ChatWebhookIngestor] STATUS-ONLY path', [
                'status_message_id' => $statusMessageId,
                'status_value' => $statusValue,
                'has_status_payload' => $statusPayload !== null,
            ]);

            if ($statusMessageId && $statusValue) {
                $statusMessage = [
                    'id' => $statusMessageId,
                    'status' => $statusValue,
                    'window' => is_array($statusPayload['window'] ?? null) ? $statusPayload['window'] : null,
                    'errors' => is_array($statusPayload['errors'] ?? null) ? $statusPayload['errors'] : null,
                ];

                $updated = $this->updateExistingMessageStatus(
                    $tenantId,
                    (string) $statusMessageId,
                    $statusMessage,
                    $this->stringOrNull($payload['instance_id'] ?? null),
                );

                if ($updated) {
                    return;
                }
            }

            return;
        }

        $externalId = $message['id'] ?? $message['messageid'] ?? null;

        if ($externalId) {
            $statusUpdatePayload = $message;
            if ($this->updateExistingMessageStatus(
                $tenantId,
                $externalId,
                $statusUpdatePayload,
                $this->stringOrNull($payload['instance_id'] ?? null),
            )) {
                return;
            }
        }

        $remoteJid = $message['chatid'] ?? $message['from'] ?? $payload['remote_jid'] ?? null;
        $body = $message['body'] ?? $message['text'] ?? ($message['content']['text'] ?? ($message['caption'] ?? ''));
        $type = $message['type'] ?? $message['mediaType'] ?? $message['messageType'] ?? 'text';
        $direction = $payload['direction'] ?? ((isset($message['fromMe']) && $message['fromMe']) ? 'outgoing' : 'incoming');

        // Extrair URL de mídia - UazAPI usa message.content/media.URL para arquivos
        $content = is_array($message['content'] ?? null)
            ? $message['content']
            : (is_array($message['media'] ?? null) ? $message['media'] : []);
        $rawMessage = is_array($payload['raw']['message'] ?? null) ? $payload['raw']['message'] : [];
        $rawContent = is_array($rawMessage['content'] ?? null) ? $rawMessage['content'] : [];
        $fileUrl = $message['mediaUrl']
            ?? $message['fileUrl']
            ?? $message['url']
            ?? ($content['URL'] ?? null)
            ?? ($content['url'] ?? null);

        $mimeType = $message['mimetype'] ?? $message['mimeType'] ?? ($content['mimetype'] ?? null);
        $fileName = $message['fileName'] ?? $message['filename'] ?? ($content['fileName'] ?? ($content['filename'] ?? null));
        $fileSize = $message['fileSize'] ?? $message['size'] ?? ($content['fileLength'] ?? null);

        // Fallback: generate fileName from mimeType when missing
        if (! $fileName && $mimeType) {
            $ext = $this->guessExtensionFromMimeType((string) $mimeType);
            $fileName = ($type === 'audio' || $type === 'ptt') ? "audio{$ext}" : "file{$ext}";
        }

        // IDs para download de mídia
        $messageId = $message['messageid'] ?? $externalId ?? null;
        $instanceToken = $payload['instance_webhook_token'] ?? $payload['token'] ?? null;

        /**
         * Normalização de tipos específicos de mídia para
         * categorias simplificadas pelo sistema.
         */
        $typeLow = strtolower((string) $type);
        if (in_array($typeLow, ['ptt', 'audiomessage', 'voice', 'audio'], true) || str_starts_with((string) $mimeType, 'audio/')) {
            $type = 'audio';
        } elseif (in_array($typeLow, ['image', 'picture', 'imagemessage'], true) || str_starts_with((string) $mimeType, 'image/')) {
            $type = 'image';
        } elseif (in_array($typeLow, ['video', 'videomessage'], true) || str_starts_with((string) $mimeType, 'video/')) {
            $type = 'video';
        } elseif (in_array($typeLow, ['sticker', 'stickermessage'], true)) {
            $type = 'sticker';
        } elseif (in_array($typeLow, ['document', 'file', 'doc', 'media', 'documentmessage'], true)) {
            $type = 'file';
        }

        if (str_contains($typeLow, 'location')) {
            $type = 'location';
        }

        // Normalize metadata for contact messages
        // Uazapi sends contact arrays as type='media' with mediaType='contact_array' / messageType='ContactsArrayMessage'
        // Also detect by content structure: contacts[] array with vcard entries
        $mediaContent = is_array($message['media'] ?? null) ? $message['media'] : [];
        $hasContactContent = is_array($content['contacts'] ?? null) && ! empty($content['contacts'])
            || is_array($rawContent['contacts'] ?? null) && ! empty($rawContent['contacts'])
            || is_array($mediaContent['contacts'] ?? null) && ! empty($mediaContent['contacts']);
        $rawMsgType = strtolower((string) ($message['type'] ?? ''));
        $rawMediaType = strtolower((string) ($message['mediaType'] ?? ''));
        $rawMessageType = strtolower((string) ($message['messageType'] ?? ''));
        $rawSourceType = strtolower((string) ($rawMessage['type'] ?? ''));
        $rawSourceMediaType = strtolower((string) ($rawMessage['mediaType'] ?? ($rawMessage['media_type'] ?? '')));
        $rawSourceMessageType = strtolower((string) ($rawMessage['messageType'] ?? ($rawMessage['message_type'] ?? '')));
        $contactTypeTokens = ['contactmessage', 'contact', 'contacts', 'contact_array', 'contactsarraymessage'];
        $contactMeta = null;
        if ($type === 'contact'
            || in_array($rawMsgType, $contactTypeTokens, true)
            || in_array($rawMediaType, $contactTypeTokens, true)
            || in_array($rawMessageType, $contactTypeTokens, true)
            || in_array($rawSourceType, $contactTypeTokens, true)
            || in_array($rawSourceMediaType, $contactTypeTokens, true)
            || in_array($rawSourceMessageType, $contactTypeTokens, true)
            || $hasContactContent
        ) {
            $type = 'contact';
            $contactMeta = $this->extractContactMetadata([
                ...$message,
                'rawMessage' => $rawMessage,
            ]);
        }

        // Normalize metadata for location messages
        $locationMeta = null;
        if ($type === 'location') {
            $locationMeta = $this->extractLocationMetadata($message);
        }

        // Disparar download assíncrono para mensagens de mídia (alinha com legado)
        $needsAsyncDownload = $messageId && $instanceToken && in_array($type, ['file', 'audio', 'image', 'video', 'sticker'], true);

        if ($needsAsyncDownload) {
            logger()->info('[ChatWebhookIngestor] Media marked for async download', [
                'message_id' => $messageId,
                'original_url' => $fileUrl,
            ]);
        }

        /**
         * Verificação de Idempotência.
         *
         * Com `instance_id` válido: reserva atômica na tabela de identidade
         * (tenant + instância + external ID). INSERT ... ON CONFLICT DO
         * NOTHING é a fronteira atômica: duas reentregas concorrentes do mesmo
         * WAMID disputam a MESMA chave única e apenas uma vence. O mesmo
         * external_id em instâncias distintas NÃO colide.
         *
         * Sem `instance_id` (payloads legados uazapi sem instância resolvida):
         * cai no `exists()` por tenant + external_id — sem atomicidade, mas
         * preserva o comportamento histórico e evita gravar '' numa coluna UUID.
         */
        if ($externalId) {
            $instanceIdForIdentity = $this->stringOrNull($payload['instance_id'] ?? null);

            if ($instanceIdForIdentity === null || $instanceIdForIdentity === '') {
                if (ChatMessage::query()
                    ->where('tenant_id', $tenantId)
                    ->where('external_id', $externalId)
                    ->exists()) {
                    return;
                }
            } else {
                $reserved = DB::table('chat_message_identities')->insertOrIgnore([
                    'id' => (string) Str::uuid(),
                    'tenant_id' => $tenantId,
                    'instance_id' => $instanceIdForIdentity,
                    'external_id' => (string) $externalId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if ($reserved === 0) {
                    // Conflito na reserva: pode ser (a) reentrega legítima da Meta
                    // (mensagem já persistida — skip) ou (b) reserva ÓRFÃ de uma
                    // tentativa anterior que falhou antes de criar a mensagem
                    // (message_id nulo — reprocessar em vez de engolir o WAMID).
                    $orphan = DB::table('chat_message_identities')
                        ->where('tenant_id', $tenantId)
                        ->where('instance_id', $instanceIdForIdentity)
                        ->where('external_id', (string) $externalId)
                        ->whereNull('message_id')
                        ->exists();

                    if (! $orphan) {
                        logger()->debug('[ChatWebhookIngestor] Duplicate identity reserved — skipping message', [
                            'tenant_id' => $tenantId,
                            'instance_id' => $instanceIdForIdentity,
                            'external_id' => $externalId,
                        ]);

                        return;
                    }

                    logger()->warning('[ChatWebhookIngestor] Orphan identity found — reprocessing message', [
                        'tenant_id' => $tenantId,
                        'instance_id' => $instanceIdForIdentity,
                        'external_id' => $externalId,
                    ]);
                }
            }
        }

        $ticketDto = ChatTicketDTO::fromArray([
            'channel' => $payload['channel'] ?? 'whatsapp',
            'contact_id' => $payload['contact_id'] ?? null,
            'instance_id' => $payload['instance_id'] ?? null,
            'remote_jid' => $remoteJid,
            'subject' => $payload['subject'] ?? null,
            'priority' => $payload['priority'] ?? 'normal',
            'push_name' => $payload['push_name']
                ?? ($message['senderName'] ?? null)
                ?? ($payload['chat']['name'] ?? null)
                ?? ($payload['chat']['wa_name'] ?? null),
            'profile_picture_url' => filter_var($payload['profile_picture_url'] ?? '', FILTER_VALIDATE_URL) ?: null,
            'phone' => $payload['phone'] ?? ($payload['chat']['phone'] ?? null),
            'phone_e164' => $payload['phone_e164'] ?? null,
        ]);

        $ticket = $this->ticketActions->findOrCreateByRemoteJid($tenantId, $ticketDto);

        // Detectar se é a primeira interação (ticket recém-criado)
        $isFirstInteraction = $ticket->wasRecentlyCreated;

        // Enrich payload with extracted contact/location metadata
        $enrichedPayload = $payload;
        if ($contactMeta) {
            $enrichedPayload['contact'] = $contactMeta;
        }
        if ($locationMeta) {
            $enrichedPayload['location'] = $locationMeta;
        }

        $messageDto = ChatMessageDTO::fromArray([
            'ticket_id' => $ticket->id,
            'content' => $body,
            'direction' => $direction,
            'type' => $type,
            'is_from_contact' => $direction === 'incoming',
            'external_id' => $externalId,
            'metadata' => $enrichedPayload,
            'file_url' => $fileUrl,
            'mime_type' => $mimeType,
            'file_name' => $fileName,
            'file_size' => $fileSize ? (int) $fileSize : null,
        ]);

        $createdMessage = $this->messageActions->create($tenantId, $messageDto);

        // Vincula a mensagem persistida à identidade reservada atomicamente.
        if ($externalId) {
            $instanceIdForIdentity = $this->stringOrNull($payload['instance_id'] ?? null);
            if ($instanceIdForIdentity !== null && $instanceIdForIdentity !== '') {
                DB::table('chat_message_identities')
                    ->where('tenant_id', $tenantId)
                    ->where('instance_id', $instanceIdForIdentity)
                    ->where('external_id', (string) $externalId)
                    ->update(['message_id' => (string) $createdMessage->id]);
            }
        }

        // Disparar job de download assíncrono se necessário
        if ($needsAsyncDownload) {
            ChatMediaDownloadJob::dispatch(
                (string) $createdMessage->id,
                $tenantId,
                (string) $instanceToken,
                (string) $messageId,
                $fileUrl,
                $mimeType
            )->onQueue('media');

            logger()->info('[ChatWebhookIngestor] Media download job dispatched', [
                'message_id' => $createdMessage->id,
                'external_message_id' => $messageId,
            ]);
        }

        if ($direction === 'incoming') {
            if ($isFirstInteraction) {
                $this->dispatchBusinessHoursAutomation($tenantId, $ticket);
            }

            $this->renewMetaWindowFromInbound($ticket, $type, $message);

            MessagePersisted::dispatch(
                $tenantId,
                (string) $ticket->id,
                (string) $body,
                [
                    'instance_id' => (string) ($payload['instance_id'] ?? ''),
                    'message_id' => (string) $createdMessage->id,
                    'message_type' => (string) ($type ?: 'text'),
                    'is_first_interaction' => $isFirstInteraction,
                    'direction' => 'incoming',
                ],
            );
        }
    }

    /**
     * Atualiza o status de conexão da instância (online/offline).
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<string, mixed>  $payload  Dados do evento de conexão.
     */
    private function updateInstanceConnectionStatus(string $tenantId, array $payload): void
    {
        $token = $this->extractInstanceToken($payload);
        logger()->debug('[ChatWebhookIngestor] updateInstanceConnectionStatus', [
            'token' => $token,
            'raw_instance' => data_get($payload, 'raw.instance'),
            'raw_status' => data_get($payload, 'raw.status'),
        ]);
        if (! $token) {
            logger()->warning('[ChatWebhookIngestor] No token found for connection event');

            return;
        }

        $instance = ChatInstance::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($token): void {
                $query->where('webhook_token', $token)
                    ->orWhereRaw("settings_json->>'token' = ?", [$token]);
            })
            ->first();

        if (! $instance) {
            logger()->warning('[ChatWebhookIngestor] Instance not found for token', ['token' => $token]);

            return;
        }

        $status = $this->normalizeConnectionStatus($payload);
        logger()->debug('[ChatWebhookIngestor] Updating instance status', [
            'instance_id' => $instance->id,
            'old_status' => $instance->status,
            'new_status' => $status,
        ]);
        $settings = $instance->settings_json ?? [];
        $settings['last_connection'] = [
            'status' => $status,
            'connected' => data_get($payload, 'raw.status.connected'),
            'logged_in' => data_get($payload, 'raw.status.loggedIn'),
            'instance' => data_get($payload, 'raw.instance'),
            'updated_at' => now()->toIso8601String(),
        ];

        $instance->status = $status;
        $instance->last_status_at = now();
        $instance->settings_json = $settings;
        $instance->save();

        // Atualizar também a tabela platform_uazapi_instances (usada pelo frontend Admin)
        $this->updatePlatformUazapiInstanceStatus($tenantId, $token, $status);
    }

    /**
     * Atualiza o status na tabela platform_uazapi_instances.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $token  Token da instância.
     * @param  string  $status  Novo status.
     */
    private function updatePlatformUazapiInstanceStatus(string $tenantId, string $token, string $status): void
    {
        $platformInstance = PlatformUazapiInstance::query()
            ->where('tenant_id', $tenantId)
            ->where('token', $token)
            ->first();

        if (! $platformInstance) {
            logger()->debug('[ChatWebhookIngestor] PlatformUazapiInstance not found for token', ['token' => $token]);

            return;
        }

        logger()->debug('[ChatWebhookIngestor] Updating PlatformUazapiInstance status', [
            'instance_id' => $platformInstance->id,
            'old_status' => $platformInstance->status,
            'new_status' => $status,
        ]);

        $platformInstance->status = $status;
        $platformInstance->last_status_at = now();
        $platformInstance->save();
    }

    /**
     * Extrai o token da instância de diversos locais possíveis no payload.
     *
     * @param  array<string, mixed>  $payload  Dados do evento.
     * @return string|null Token encontrado ou null.
     */
    private function extractInstanceToken(array $payload): ?string
    {
        return $payload['instance_webhook_token']
            ?? data_get($payload, 'token')
            ?? data_get($payload, 'raw.token')
            ?? data_get($payload, 'raw.instance.token');
    }

    /**
     * Normaliza os diferentes formatos de status de conexão dos gateways.
     *
     * @param  array<string, mixed>  $payload  Dados do evento.
     * @return string Status normalizado.
     */
    private function normalizeConnectionStatus(array $payload): string
    {
        $statusPayload = data_get($payload, 'raw.instance.status')
            ?? data_get($payload, 'raw.status')
            ?? data_get($payload, 'status');

        return $this->resolveStatusValue($statusPayload);
    }

    /**
     * Resolve o valor textual do status a partir de tipos variados (string, bool, array).
     *
     * @param  mixed  $value  Valor bruto do status.
     * @return string Valor textual resolvido.
     */
    private function resolveStatusValue(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'connected' : 'disconnected';
        }

        if (is_array($value)) {
            if (isset($value['status']) && is_string($value['status'])) {
                return $value['status'];
            }

            if (isset($value['connected']) && is_bool($value['connected'])) {
                return $value['connected'] ? 'connected' : 'disconnected';
            }

            if (isset($value['loggedIn']) && is_bool($value['loggedIn'])) {
                return $value['loggedIn'] ? 'connected' : 'disconnected';
            }
        }

        return 'disconnected';
    }

    /**
     * Atualiza os carimbos de entrega e leitura para mensagens existentes.
     *
     * A busca é escopada por tenant + instância (via ticket) + external ID:
     * o mesmo WAMID em duas instâncias distintas nunca atualiza a mensagem
     * da instância errada.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  string  $externalId  ID da mensagem no provedor externo.
     * @param  array<string, mixed>  $message  Dados da mensagem no payload.
     * @param  string|null  $instanceId  Instância de origem, quando disponível.
     * @return bool True se a mensagem foi encontrada e atualizada.
     */
    private function updateExistingMessageStatus(
        string $tenantId,
        string $externalId,
        array $message,
        ?string $instanceId = null,
    ): bool {
        $query = ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('external_id', $externalId);

        if ($instanceId !== null && $instanceId !== '') {
            $query->whereHas('ticket', function ($ticketQuery) use ($instanceId): void {
                $ticketQuery->where('instance_id', $instanceId);
            });
        }

        $existing = $query->first();

        logger()->debug('[ChatWebhookIngestor] updateExistingMessageStatus lookup', [
            'tenant_id' => $tenantId,
            'instance_id' => $instanceId,
            'external_id_query' => $externalId,
            'found' => $existing !== null,
            'ack_value' => $message['ack'] ?? $message['status'] ?? null,
        ]);

        if (! $existing) {
            return false;
        }

        $originalStatus = $existing->status;
        $ack = $message['ack'] ?? $message['status'] ?? null;
        if (is_numeric($ack)) {
            $ack = (int) $ack;
            if ($ack >= 1) {
                $existing->status = 'sent';
                $existing->sent_at = $existing->sent_at ?? now();
            }
            if ($ack >= 2) {
                $existing->status = 'delivered';
                $existing->delivered_at = $existing->delivered_at ?? now();
            }
            if ($ack >= 3) {
                $existing->status = 'read';
                $existing->read_at = $existing->read_at ?? now();
            }
        } elseif (is_string($ack)) {
            $map = [
                'sent' => 'sent',
                'delivered' => 'delivered',
                'read' => 'read',
                'deleted' => 'deleted',
                'revoke' => 'deleted',
                'revoked' => 'deleted',
                // Status 'failed' da Meta NUNCA é mascarado como 'sent' —
                // ver ~/Documents/prompts/SKILLS/meta-whatsapp-expert.
                'failed' => 'failed',
            ];
            $existing->status = $map[strtolower($ack)] ?? $existing->status;
            if ($existing->status === 'sent' && ! $existing->sent_at) {
                $existing->sent_at = now();
            }
            if ($existing->status === 'delivered' && ! $existing->delivered_at) {
                $existing->delivered_at = now();
            }
            if ($existing->status === 'read' && ! $existing->read_at) {
                $existing->read_at = now();
            }
            if ($existing->status === 'deleted' && ! $existing->deleted_at) {
                $existing->is_deleted = true;
                $existing->deleted_at = now();
                if (! $existing->deleted_by) {
                    $metadata = $existing->metadata ?? [];
                    $metadata['deleted_by'] = 'remote';
                    $existing->metadata = $metadata;
                }
            }
            if ($existing->status === 'failed' && is_array($message['errors'] ?? null)) {
                $metadata = $existing->metadata ?? [];
                $metadata['errors'] = $message['errors'];
                $existing->metadata = $metadata;
            }
        }

        $existing->save();

        $this->applyMetaWindowFromStatus($existing, $message);

        logger()->debug('[ChatWebhookIngestor] Message status updated', [
            'message_id' => $existing->id,
            'external_id' => $existing->external_id,
            'status' => $existing->status,
            'delivered_at' => $existing->delivered_at?->toIso8601String(),
            'read_at' => $existing->read_at?->toIso8601String(),
        ]);

        /**
         * Notificação via WebSocket.
         *
         * Emite o evento se o status da mensagem mudou significativamente.
         */
        if ($existing->status !== $originalStatus) {
            if ($existing->status === 'deleted') {
                $this->broadcastService->emitMessageDelete([
                    'message_id' => (string) $existing->id,
                    'ticket_id' => (string) $existing->ticket_id,
                    'tenant_id' => $tenantId,
                    'status' => 'deleted',
                    'deleted_at' => $existing->deleted_at?->toIso8601String(),
                    'deleted_by' => $existing->deleted_by,
                ]);

                return true;
            }
            $this->broadcastService->emitMessageStatus([
                'message_id' => (string) $existing->id,
                'ticket_id' => (string) $existing->ticket_id,
                'tenant_id' => $tenantId,
                'status' => $existing->status,
                'sent_at' => $existing->sent_at?->toIso8601String(),
                'delivered_at' => $existing->delivered_at?->toIso8601String(),
                'read_at' => $existing->read_at?->toIso8601String(),
            ]);
        }

        return true;
    }

    /**
     * Disparar automações de horário de expediente na primeira interação inbound.
     */
    private function dispatchBusinessHoursAutomation(string $tenantId, \Domain\Chat\Models\ChatTicket $ticket): void
    {
        $now = now();
        $currentDay = $now->dayOfWeek;
        $currentTime = $now->format('H:i');

        $todaySchedules = ConfigurationOpeningHour::query()
            ->forTenant($tenantId)
            ->active()
            ->where('day_of_week', $currentDay)
            ->get(['id', 'open_time', 'close_time']);

        if ($todaySchedules->isEmpty()) {
            $this->ticketActions->sendConfiguredSystemMessage(
                $ticket,
                'send_no_business_hours_message',
                'no_business_hours_message',
                'no_business_hours_message'
            );

            return;
        }

        $isOpenNow = $todaySchedules->contains(function (ConfigurationOpeningHour $schedule) use ($currentTime): bool {
            return $schedule->open_time <= $currentTime && $schedule->close_time >= $currentTime;
        });

        if (! $isOpenNow) {
            $this->ticketActions->sendConfiguredSystemMessage(
                $ticket,
                'send_outside_business_hours_message',
                'outside_business_hours_message',
                'outside_business_hours_message'
            );
        }
    }

    /**
     * Renova a janela de atendimento Meta (24h/72h CTWA) a partir de um inbound.
     *
     * Só se aplica a instâncias do provedor `meta` e mensagens de conteúdo
     * real (nunca `type === 'system'`). Roda em try/catch: qualquer falha
     * apenas loga e segue — nunca pode derrubar a ingestão da mensagem,
     * pois isso é produção viva.
     *
     * @param  array<string, mixed>  $message  Payload da mensagem inbound normalizada.
     */
    private function renewMetaWindowFromInbound(ChatTicket $ticket, string $type, array $message): void
    {
        try {
            $instance = $ticket->instance;

            if (! $instance instanceof ChatInstance || $instance->provider !== 'meta') {
                return;
            }

            if ($type === 'system') {
                return;
            }

            $timestamp = $message['timestamp'] ?? null;
            $referral = is_array($message['referral'] ?? null) ? $message['referral'] : null;

            $this->metaWindowService->renewFromInbound(
                $ticket,
                is_string($timestamp) ? $timestamp : null,
                $referral,
            );
        } catch (\Throwable $exception) {
            logger()->error('[ChatWebhookIngestor] Falha ao renovar janela Meta a partir do inbound', [
                'ticket_id' => (string) $ticket->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Aplica a janela de atendimento Meta a partir de `status.window`, quando presente.
     *
     * Chamado após a atualização de status da mensagem existente. Roda em
     * try/catch: falha aqui apenas loga e segue — nunca derruba o
     * processamento do status da mensagem.
     *
     * @param  array<string, mixed>  $statusPayload  Payload de status normalizado (pode conter `window`).
     */
    private function applyMetaWindowFromStatus(ChatMessage $chatMessage, array $statusPayload): void
    {
        $window = is_array($statusPayload['window'] ?? null) ? $statusPayload['window'] : null;

        if ($window === null) {
            return;
        }

        $expiresAt = $window['expiresAt'] ?? null;
        $windowType = $window['type'] ?? null;

        if (! is_string($expiresAt) || ! is_string($windowType)) {
            return;
        }

        try {
            $ticket = $chatMessage->ticket;

            if (! $ticket instanceof ChatTicket) {
                return;
            }

            $this->metaWindowService->applyFromStatus($ticket, $expiresAt, $windowType);
        } catch (\Throwable $exception) {
            logger()->error('[ChatWebhookIngestor] Falha ao aplicar janela Meta a partir do status', [
                'message_id' => (string) $chatMessage->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    /**
     * Extrair dados de contato do payload do webhook.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>|null
     */
    private function extractContactMetadata(array $message): ?array
    {
        $rawMessage = is_array($message['rawMessage'] ?? null) ? $message['rawMessage'] : [];

        // Gateway normalizes content object into 'media' key, so check both
        $content = is_array($message['content'] ?? null)
            ? $message['content']
            : (is_array($message['media'] ?? null)
                ? $message['media']
                : (is_array($rawMessage['content'] ?? null) ? $rawMessage['content'] : []));

        // Try vcard JSON format
        $vcardJson = $message['vcardJson'] ?? $content['vcardJson'] ?? null;
        if (is_array($vcardJson)) {
            $first = is_array($vcardJson[0] ?? null) ? $vcardJson[0] : $vcardJson;
            $phones = [];
            foreach (($first['phones'] ?? []) as $phone) {
                if (is_array($phone)) {
                    $phones[] = $phone['value'] ?? $phone['number'] ?? '';
                } elseif (is_string($phone)) {
                    $phones[] = $phone;
                }
            }

            return array_filter([
                'fullName' => $first['displayName'] ?? $first['fullName'] ?? $first['fn'] ?? null,
                'phoneNumber' => $phones[0] ?? null,
                'organization' => $first['org'] ?? $first['organization'] ?? null,
                'email' => is_array($first['emails'] ?? null) ? ($first['emails'][0]['value'] ?? $first['emails'][0] ?? null) : ($first['email'] ?? null),
            ]);
        }

        // Try contacts array format (Uazapi: content.contacts[{displayName, vcard}])
        $contacts = $content['contacts'] ?? $message['contacts'] ?? ($rawMessage['contacts'] ?? null);
        if (is_array($contacts) && count($contacts) > 0) {
            $contacts = array_values($contacts);
            $allContacts = [];
            foreach ($contacts as $c) {
                if (! is_array($c)) {
                    continue;
                }
                $phones = [];
                if (! empty($c['phones']) && is_array($c['phones'])) {
                    // Structured phones array (e.g. Zapi format)
                    foreach ($c['phones'] as $phone) {
                        if (is_array($phone)) {
                            $phones[] = ['number' => $phone['phone'] ?? $phone['value'] ?? $phone['number'] ?? '', 'type' => $phone['type'] ?? null];
                        }
                    }
                } elseif (isset($c['vcard']) && is_string($c['vcard'])) {
                    // Parse TEL lines and their X-ABLabel from vCard string (Uazapi format)
                    // e.g. "item1.TEL;waid=55...:+55 11 91085-0535\nitem1.X-ABLabel:Celular"
                    preg_match_all('/(?:item(\d+)\.)?TEL[^:]*:([^\r\n]+)/i', $c['vcard'], $telMatches, PREG_SET_ORDER);
                    preg_match_all('/item(\d+)\.X-ABLabel:([^\r\n]+)/i', $c['vcard'], $labelMatches, PREG_SET_ORDER);
                    $labelMap = [];
                    foreach ($labelMatches as $lm) {
                        $labelMap[(int) $lm[1]] = trim($lm[2]);
                    }
                    foreach ($telMatches as $tm) {
                        $itemIndex = (int) $tm[1];
                        $phones[] = [
                            'number' => trim($tm[2]),
                            'type' => $labelMap[$itemIndex] ?? null,
                        ];
                    }
                }

                // Trim display name — Uazapi sometimes prepends a space
                $fullName = trim((string) ($c['name']['formattedName'] ?? $c['name']['name'] ?? $c['displayName'] ?? '')) ?: null;

                $allContacts[] = array_filter([
                    'fullName' => $fullName,
                    'phoneNumber' => $phones[0]['number'] ?? null,
                    'phones' => ! empty($phones) ? $phones : null,
                    'organization' => $c['org']['company'] ?? $c['organization'] ?? null,
                    'email' => is_array($c['emails'] ?? null) ? ($c['emails'][0]['email'] ?? null) : null,
                ]);
            }

            if (count($allContacts) === 1) {
                return $allContacts[0];
            }

            return ['contacts' => $allContacts, ...$allContacts[0]];
        }

        // Try vcard string format
        $vcard = $content['vcard'] ?? $message['vcard'] ?? ($rawMessage['vcard'] ?? null);
        if (is_string($vcard) && str_contains($vcard, 'VCARD')) {
            return $this->parseVcard($vcard);
        }

        return null;
    }

    /**
     * Extrair dados de localização do payload do webhook.
     *
     * @param  array<string, mixed>  $message
     * @return array<string, mixed>|null
     */
    private function extractLocationMetadata(array $message): ?array
    {
        $content = is_array($message['content'] ?? null) ? $message['content'] : [];

        $lat = $content['degreesLatitude'] ?? $content['latitude'] ?? $message['latitude'] ?? null;
        $lng = $content['degreesLongitude'] ?? $content['longitude'] ?? $message['longitude'] ?? null;

        if ($lat === null && $lng === null) {
            return null;
        }

        return array_filter([
            'latitude' => is_numeric($lat) ? (float) $lat : null,
            'longitude' => is_numeric($lng) ? (float) $lng : null,
            'name' => $content['name'] ?? $message['locationName'] ?? null,
            'address' => $content['address'] ?? $message['address'] ?? null,
        ]);
    }

    /**
     * Parse vCard string format to extract contact metadata.
     *
     * @return array<string, mixed>|null
     */
    private function parseVcard(string $vcard): ?array
    {
        $name = null;
        $phone = null;
        $org = null;
        $email = null;

        if (preg_match('/FN[;:](.+)/i', $vcard, $m)) {
            $name = trim($m[1]);
        }
        if (preg_match('/TEL[^:]*:(.+)/i', $vcard, $m)) {
            $phone = trim($m[1]);
        }
        if (preg_match('/ORG[;:](.+)/i', $vcard, $m)) {
            $org = trim($m[1]);
        }
        if (preg_match('/EMAIL[^:]*:(.+)/i', $vcard, $m)) {
            $email = trim($m[1]);
        }

        if (! $name && ! $phone) {
            return null;
        }

        return array_filter([
            'fullName' => $name,
            'phoneNumber' => $phone,
            'organization' => $org,
            'email' => $email,
        ]);
    }

    /**
     * Inferir extensão a partir do MIME type.
     *
     * @return string Extensão com ponto (ex: '.pdf')
     */
    private function guessExtensionFromMimeType(string $mimeType): string
    {
        $map = [
            'image/jpeg' => '.jpg',
            'image/png' => '.png',
            'image/gif' => '.gif',
            'image/webp' => '.webp',
            'video/mp4' => '.mp4',
            'video/3gpp' => '.3gp',
            'audio/ogg' => '.ogg',
            'audio/mpeg' => '.mp3',
            'audio/mp4' => '.m4a',
            'audio/opus' => '.opus',
            'audio/webm' => '.webm',
            'application/pdf' => '.pdf',
            'application/msword' => '.doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => '.docx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => '.xlsx',
            'text/plain' => '.txt',
        ];

        return $map[$mimeType] ?? '';
    }

    /**
     * Despacha {@see MetaTemplateStatusUpdated} a partir do payload normalizado
     * pelo Gateway (event_type='meta.template.status_updated').
     *
     * Aceita campos no nível raiz ou aninhados em `template.*` para tolerar
     * pequenas variações entre versões do normalizer do Gateway. Quando o
     * payload não traz `instance_id` (lookup falhou no Gateway), apenas loga
     * e retorna — sem disparar evento incompleto.
     *
     * @param  array<string, mixed>  $payload
     */
    private function dispatchMetaTemplateStatusUpdated(string $tenantId, array $payload): void
    {
        $template = is_array($payload['template'] ?? null) ? $payload['template'] : [];

        $instanceId = $this->stringOrEmpty($payload['instance_id'] ?? null);
        $name = $this->stringOrEmpty($template['name'] ?? $payload['name'] ?? null);
        $language = $this->stringOrEmpty($template['language'] ?? $payload['language'] ?? null);
        $status = $this->stringOrEmpty(
            $template['status']
            ?? $payload['status']
            ?? $template['event']
            ?? $payload['event']
            ?? null
        );

        if ($instanceId === '' || $name === '' || $language === '' || $status === '') {
            logger()->warning('[ChatWebhookIngestor] meta.template.status_updated payload incompleto', [
                'tenant_id' => $tenantId,
                'has_instance_id' => $instanceId !== '',
                'has_name' => $name !== '',
                'has_language' => $language !== '',
                'has_status' => $status !== '',
            ]);

            return;
        }

        $rejectedReason = $this->stringOrNull($template['reason'] ?? $payload['reason'] ?? null);
        $externalId = $this->stringOrNull($template['external_id'] ?? $payload['external_id'] ?? null);

        event(new MetaTemplateStatusUpdated(
            tenantId: $tenantId,
            instanceId: $instanceId,
            templateName: $name,
            language: $language,
            status: $status,
            rejectedReason: $rejectedReason,
            externalId: $externalId,
        ));
    }

    private function stringOrEmpty(mixed $value): string
    {
        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return '';
    }

    private function stringOrNull(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $string = $this->stringOrEmpty($value);

        return $string === '' ? null : $string;
    }
}
