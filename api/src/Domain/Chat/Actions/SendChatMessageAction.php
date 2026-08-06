<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\DTOs\ChatMessageDTO;
use Domain\Chat\Events\MessagePersisted;
use Domain\Chat\Models\ChatInstance;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatSession;
use Domain\Chat\Models\ChatTicket;
use Domain\Chat\Services\ChatGatewayService;
use Domain\Chat\Services\WebChatRedisPublisher;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Action de envio de mensagens de chat.
 *
 * Responsável pela criação de mensagens no banco, despacho para gateways externos
 * (WhatsApp), envio de contatos, localização e integração com o ciclo de vida
 * do ticket (human takeover, status updates).
 *
 * @category Actions
 */
final readonly class SendChatMessageAction
{
    public function __construct(
        private ChatGatewayService $gateway,
        private ChatTicketActions $ticketActions,
        private ProcessChatMessageAction $processAction,
        private WebChatRedisPublisher $webChatPublisher,
        private VerifyContactWindowAction $verifyWindowAction,
    ) {}

    /**
     * Registrar uma nova mensagem no banco e despachar para o gateway (se for saída).
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  ChatMessageDTO  $dto  Objeto de transferência com dados da mensagem.
     * @return ChatMessage Modelo da mensagem criada.
     */
    public function create(string $tenantId, ChatMessageDTO $dto): ChatMessage
    {
        $this->guardMetaWindow($tenantId, $dto);

        $payload = $dto->toArray();
        $extendedAttributes = [
            'file_url' => $payload['file_url'] ?? null,
            'file_name' => $payload['file_name'] ?? null,
            'mime_type' => $payload['mime_type'] ?? null,
            'file_size' => $payload['file_size'] ?? null,
        ];

        unset(
            $payload['file_url'],
            $payload['file_name'],
            $payload['mime_type'],
            $payload['file_size'],
        );

        $message = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            ...$payload,
            'tenant_id' => $tenantId,
            'status' => $dto->direction === 'outgoing' ? 'pending' : 'received',
        ]);

        foreach ($extendedAttributes as $key => $value) {
            if ($value !== null) {
                $message->{$key} = $value;
            }
        }

        if (array_filter($extendedAttributes, static fn (mixed $value): bool => $value !== null) !== []) {
            $message->save();
        }

        $ticket = $message->ticket;
        if ($ticket instanceof ChatTicket) {
            $ticket->last_message_at = now();
            $ticket->save();

            // Ativar Human Takeover automaticamente quando um agente envia mensagem
            if ($dto->source === ChatMessageDTO::SOURCE_AGENT) {
                $this->ticketActions->activateHumanTakeover(
                    $tenantId,
                    (string) $ticket->id,
                    $dto->userId
                );

                $ticket->refresh();
            }

            if ($dto->direction === 'outgoing'
                && $dto->type !== 'internal_note'
                && $dto->source !== ChatMessageDTO::SOURCE_WEBHOOK
            ) {
                if (! $ticket->relationLoaded('contact')) {
                    $ticket->load('contact');
                }

                // Verificar se é um ticket webchat — se sim, entregar ao visitante via WebChat publisher
                // em vez de enviar para o gateway externo (WhatsApp).
                $webchatSession = ChatSession::query()
                    ->where('ticket_id', (string) $ticket->id)
                    ->first();

                if ($webchatSession !== null) {
                    $this->publishAgentMessageToWebChat(
                        session: $webchatSession,
                        message: $message,
                        tenantId: $tenantId,
                    );
                } else {
                    $this->sendToGateway($message, $ticket);
                }
            }

            // Dispatch MessagePersisted for outgoing direction to trigger
            // activity-tracking listeners (last_agent_message_at, last_message_at).
            if ($dto->direction === 'outgoing') {
                MessagePersisted::dispatch(
                    $tenantId,
                    (string) $ticket->id,
                    $dto->content,
                    [
                        'instance_id' => (string) $ticket->instance_id,
                        'message_id' => (string) $message->id,
                        'message_type' => $dto->type,
                        'is_first_interaction' => false,
                        'direction' => 'outgoing',
                    ],
                );
            }
        }

        // Emit new message event for incoming messages (from contact) and
        // for outgoing messages from agent/AI/bot (so the attendant UI and the
        // ticket list update in real time when the AI/bot answers a customer).
        if ($dto->direction === 'incoming'
            || $dto->type === 'internal_note'
            || $dto->source === ChatMessageDTO::SOURCE_AGENT
            || $dto->source === ChatMessageDTO::SOURCE_AI
            || $dto->source === ChatMessageDTO::SOURCE_BOT
        ) {
            if ($ticket instanceof ChatTicket) {
                $ticket->load(['latestMessage', 'contact']);
                $this->processAction->emitNewMessageEvent($message, $ticket);
            } else {
                $this->processAction->emitNewMessageEvent($message, null);
            }
        }

        return $message;
    }

    /**
     * Enviar cartão de contato (vCard).
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  ChatMessageDTO  $dto  Dados da mensagem com metadados do contato.
     * @return ChatMessage Mensagem criada.
     */
    public function sendContact(string $tenantId, ChatMessageDTO $dto): ChatMessage
    {
        $message = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            ...$dto->toArray(),
            'tenant_id' => $tenantId,
            'type' => 'contact',
            'status' => 'pending',
        ]);

        $ticket = $message->ticket;
        if (! $ticket instanceof ChatTicket) {
            $message->status = 'failed';
            $message->error_message = 'Ticket not found';
            $message->save();

            return $message;
        }

        $instance = $ticket->instance;
        $token = $instance?->webhook_token;
        $number = $this->resolveNumber($ticket);

        if (! $token || ! $number) {
            $message->status = 'failed';
            $message->error_message = 'Missing token or number';
            $message->save();

            return $message;
        }

        $contactData = $dto->metadata['contact'] ?? [];

        try {
            $response = $this->gateway->sendContact($token, [
                'number' => $number,
                'fullName' => $contactData['fullName'] ?? $contactData['name'] ?? 'Contato',
                'phoneNumber' => $contactData['phoneNumber'] ?? $contactData['phone'] ?? '',
                'organization' => $contactData['organization'] ?? null,
                'email' => $contactData['email'] ?? null,
                'url' => $contactData['url'] ?? null,
            ]);

            $message->status = 'sent';
            $message->sent_at = now();
            $message->external_id = $response['messageid'] ?? $response['id'] ?? null;
            $message->save();
        } catch (\Throwable $e) {
            $message->status = 'failed';
            $message->error_message = $e->getMessage();
            $message->save();
        }

        $this->processAction->emitMessageStatusEvent($message);

        return $message;
    }

    /**
     * Enviar localização geográfica.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  ChatMessageDTO  $dto  Dados da mensagem com metadados de localização.
     * @return ChatMessage Mensagem criada.
     */
    public function sendLocation(string $tenantId, ChatMessageDTO $dto): ChatMessage
    {
        $message = ChatMessage::query()->create([
            'id' => (string) Str::orderedUuid(),
            ...$dto->toArray(),
            'tenant_id' => $tenantId,
            'type' => 'location',
            'status' => 'pending',
        ]);

        $ticket = $message->ticket;
        if (! $ticket instanceof ChatTicket) {
            $message->status = 'failed';
            $message->error_message = 'Ticket not found';
            $message->save();

            return $message;
        }

        $instance = $ticket->instance;
        $token = $instance?->webhook_token;
        $number = $this->resolveNumber($ticket);

        if (! $token || ! $number) {
            $message->status = 'failed';
            $message->error_message = 'Missing token or number';
            $message->save();

            return $message;
        }

        $locationData = $dto->metadata['location'] ?? [];

        try {
            $response = $this->gateway->sendLocation($token, [
                'number' => $number,
                'latitude' => (float) ($locationData['latitude'] ?? $locationData['lat'] ?? 0),
                'longitude' => (float) ($locationData['longitude'] ?? $locationData['lng'] ?? 0),
                'name' => $locationData['name'] ?? null,
                'address' => $locationData['address'] ?? null,
            ]);

            $message->status = 'sent';
            $message->sent_at = now();
            $message->external_id = $response['messageid'] ?? $response['id'] ?? null;
            $message->save();
        } catch (\Throwable $e) {
            $message->status = 'failed';
            $message->error_message = $e->getMessage();
            $message->save();
        }

        $this->processAction->emitMessageStatusEvent($message);

        return $message;
    }

    /**
     * Bloqueia envio de texto livre quando o ticket é Meta e a janela 24h expirou.
     * Aplica a AGENTE, BOT e IA — a mesma decisão por ticket/instância, sem
     * bypass por source. Template aprovado (type != text) continua permitido.
     *
     * Fail-closed: contexto desconhecido (ticket/instância ausentes ou sem
     * contato) NUNCA libera texto livre.
     */
    private function guardMetaWindow(string $tenantId, ChatMessageDTO $dto): void
    {
        if ($dto->direction !== 'outgoing' || $dto->type !== 'text') {
            return;
        }

        $guardedSources = [
            ChatMessageDTO::SOURCE_AGENT,
            ChatMessageDTO::SOURCE_BOT,
            ChatMessageDTO::SOURCE_AI,
        ];
        if (! in_array($dto->source, $guardedSources, true)) {
            return;
        }

        $ticket = ChatTicket::query()
            ->where('id', $dto->ticketId)
            ->where('tenant_id', $tenantId)
            ->with('instance')
            ->first();

        // Fail-closed: ticket ausente não libera envio.
        if (! $ticket instanceof ChatTicket) {
            throw ValidationException::withMessages([
                'message' => ['Ticket não encontrado — envio bloqueado'],
            ]);
        }

        $instance = $ticket->instance;
        if (! $instance instanceof ChatInstance || $instance->provider !== 'meta') {
            return;
        }

        $contactId = is_string($ticket->contact_id) ? $ticket->contact_id : '';
        // Fail-closed: sem contato, texto livre não é despachado.
        if ($contactId === '') {
            throw ValidationException::withMessages([
                'message' => ['Janela 24h expirada — use template'],
            ]);
        }

        $windowStatus = $this->verifyWindowAction->execute(
            $tenantId,
            $contactId,
            (string) $ticket->id,
            (string) $ticket->instance_id,
        );
        if (! $windowStatus->canSendFreeText) {
            throw ValidationException::withMessages([
                'message' => ['Janela 24h expirada — use template'],
            ]);
        }
    }

    /**
     * Publicar mensagem do atendente para o visitante webchat e marcar como entregue.
     *
     * @param  ChatSession  $session  Sessão webchat do ticket.
     * @param  ChatMessage  $message  Mensagem criada pelo atendente.
     * @param  string  $tenantId  Identificador do tenant.
     */
    private function publishAgentMessageToWebChat(
        ChatSession $session,
        ChatMessage $message,
        string $tenantId,
    ): void {
        $message->loadMissing('extended');

        $this->webChatPublisher->publishAgentMessage(
            sessionId: (string) $session->id,
            tenantId: $tenantId,
            message: $this->processAction->sanitizeMessageForRealtime($message),
        );

        $message->status = 'delivered';
        $message->sent_at = now();
        $message->delivered_at = now();
        $message->save();

        $this->processAction->emitMessageStatusEvent($message);
    }

    /**
     * Orquestrar o envio da mensagem para um gateway externo (ex: WhatsApp).
     *
     * @param  ChatMessage  $message  Mensagem a ser enviada.
     * @param  ChatTicket  $ticket  Ticket relacionado para contexto de envio.
     */
    private function sendToGateway(ChatMessage $message, ChatTicket $ticket): void
    {
        $message->loadMissing('extended');

        $instance = $ticket->instance;
        $provider = $instance instanceof ChatInstance ? $instance->provider : 'uazapi';
        $token = $instance instanceof ChatInstance ? $this->resolveInstanceToken($instance) : null;
        $instanceId = $instance instanceof ChatInstance ? (string) $instance->id : null;

        $number = $ticket->phone_e164 ?? $ticket->phone ?? $ticket->remote_jid;
        if (! $number && $ticket->contact) {
            $number = $ticket->contact->whatsapp ?? $ticket->contact->phone;
        }

        if (! $token || ! $number || ($provider === 'zapi' && ! $instanceId)) {
            $message->status = 'failed';
            $message->error_message = 'Instance token or destination missing';
            $message->save();
            $this->processAction->emitMessageStatusEvent($message);

            return;
        }

        $number = str_contains((string) $number, '@') ? Str::before($number, '@') : $number;
        $cleaned = preg_replace('/[^0-9]/', '', (string) $number);
        $number = is_string($cleaned) ? $cleaned : '';

        if ($number === '') {
            $message->status = 'failed';
            $message->error_message = 'Número destino inválido';
            $message->save();
            $this->processAction->emitMessageStatusEvent($message);

            return;
        }

        // Resolver replyid se houver quoted_message_id
        $replyId = null;
        if (isset($message->metadata['quoted_message_id'])) {
            $quoted = ChatMessage::query()
                ->select(['id', 'external_id'])
                ->where('tenant_id', (string) $message->tenant_id)
                ->where('ticket_id', (string) $message->ticket_id)
                ->where('id', (string) $message->metadata['quoted_message_id'])
                ->first();
            if ($quoted && $quoted->external_id) {
                $replyId = $quoted->external_id;
            }
        }

        try {
            $isMedia = in_array($message->type, ['file', 'video', 'image', 'sticker', 'document'], true)
                || $message->mime_type
                || $message->file_url;

            if ($provider === 'zapi') {
                if ($isMedia || $message->type === 'audio') {
                    $filePayload = $this->resolveFilePayload($message);
                    $mediaType = $message->type === 'audio' ? 'audio' : $this->resolveMediaType($message);

                    $response = $this->gateway->sendOutboundMessage('zapi', $token, $ticket->tenant_id, $instanceId, [
                        'type' => 'media',
                        'to' => $number,
                        'mediaType' => $mediaType,
                        'mediaUrl' => $filePayload['file'] ?? $message->file_url,
                        'caption' => $message->type === 'audio' ? $message->content : $this->fallbackCaption($message),
                        'fileName' => $message->file_name,
                    ]);
                } else {
                    $response = $this->gateway->sendOutboundMessage('zapi', $token, $ticket->tenant_id, $instanceId, [
                        'type' => 'text',
                        'to' => $number,
                        'text' => $message->type === 'location'
                            ? $this->buildLocationMessage($message)
                            : $this->buildOutboundText($message, $instance),
                    ]);
                }
            } elseif ($isMedia) {
                $filePayload = $this->resolveFilePayload($message);

                if (! isset($filePayload['file']) || ! is_string($filePayload['file']) || $filePayload['file'] === '') {
                    throw new \RuntimeException('Media file payload is missing');
                }

                $response = $this->gateway->sendFile($token, [
                    'number' => $number,
                    ...$filePayload,
                    'caption' => $this->fallbackCaption($message),
                    'type' => $this->resolveMediaType($message),
                    ...($message->file_name ? ['fileName' => $message->file_name] : []),
                    ...($replyId ? ['replyid' => $replyId] : []),
                ]);
            } elseif ($message->type === 'location') {
                $response = $this->gateway->sendText($token, [
                    'number' => $number,
                    'text' => $this->buildLocationMessage($message),
                    ...($replyId ? ['replyid' => $replyId] : []),
                ]);
            } elseif ($message->type === 'audio') {
                $filePayload = $this->resolveFilePayload($message);
                $response = $this->gateway->sendAudio($token, [
                    'number' => $number,
                    ...$filePayload,
                    'caption' => $message->content,
                    'type' => 'ptt',
                    ...($replyId ? ['replyid' => $replyId] : []),
                ]);
            } elseif ($message->type === 'template') {
                $response = $this->gateway->sendTemplate($token, [
                    'number' => $number,
                    'templateId' => $message->metadata['template']['name'] ?? '',
                    'language' => $message->metadata['template']['language']['code'] ?? 'pt_BR',
                    'components' => $message->metadata['template']['components'] ?? [],
                ]);
            } else {
                $response = $this->gateway->sendText($token, [
                    'number' => $number,
                    'text' => $this->buildOutboundText($message, $instance),
                    ...($replyId ? ['replyid' => $replyId] : []),
                ]);
            }

            $message->status = 'sent';
            $message->sent_at = now();
            $message->external_id = $response['messageid'] ?? $response['id'] ?? $message->external_id;
            $message->metadata = array_merge($message->metadata ?? [], ['gateway_response' => $response]);
            $message->save();
            $this->processAction->emitMessageStatusEvent($message);
        } catch (\Throwable $e) {
            $message->status = 'failed';
            $message->error_message = $e->getMessage();
            $message->save();
            $this->processAction->emitMessageStatusEvent($message);
        }
    }

    /**
     * Construir uma URL ou descrição legível de uma mensagem de localização.
     *
     * @param  ChatMessage  $message  Mensagem contendo metadados de localização.
     * @return string Descrição com link do Google Maps.
     */
    private function buildLocationMessage(ChatMessage $message): string
    {
        $lat = $message->metadata['location']['lat'] ?? $message->metadata['location']['latitude'] ?? null;
        $lng = $message->metadata['location']['lng'] ?? $message->metadata['location']['longitude'] ?? null;
        $label = $message->metadata['location']['name'] ?? $message->metadata['location']['address'] ?? 'Localização';

        if ($lat && $lng) {
            return sprintf('%s: https://maps.google.com/?q=%s,%s', $label, $lat, $lng);
        }

        return $message->content ?: 'Localização enviada';
    }

    /**
     * Definir uma legenda (caption) padrão para mensagens de mídia.
     *
     * @param  ChatMessage  $message  Mensagem contendo arquivo de mídia.
     * @return string|null Legenda formatada ou nome do tipo de arquivo.
     */
    private function fallbackCaption(ChatMessage $message): ?string
    {
        if ($message->content) {
            return $message->content;
        }

        return match ($message->type) {
            'image' => 'Imagem',
            'video' => 'Vídeo',
            'sticker' => '🧩 Sticker',
            'document' => 'Documento',
            default => null,
        };
    }

    /**
     * Monta o texto de saída para envio ao provedor.
     *
     * Quando habilitado em integrações, prefixa o nome do atendente para
     * mensagens de texto enviadas por agente.
     */
    private function buildOutboundText(ChatMessage $message, ?ChatInstance $instance): string
    {
        $content = (string) ($message->content ?? '');

        if (! $this->shouldPrefixAttendantName($message, $instance)) {
            return $content;
        }

        $attendantName = $this->resolveAttendantName($message);
        if ($attendantName === null) {
            return $content;
        }

        return sprintf("%s:\n%s", $attendantName, $content);
    }

    /**
     * Determina se a mensagem deve ser enviada com nome do atendente.
     */
    private function shouldPrefixAttendantName(ChatMessage $message, ?ChatInstance $instance): bool
    {
        $allowedSources = [ChatMessageDTO::SOURCE_AGENT, ChatMessageDTO::SOURCE_BOT, 'ai'];

        if ($message->type !== 'text' || $message->direction !== 'outgoing' || ! in_array($message->source, $allowedSources, true)) {
            return false;
        }

        if (! $instance instanceof ChatInstance) {
            return false;
        }

        $settings = $instance->settings_json ?? [];

        return (bool) ($settings['send_attendant_name'] ?? false);
    }

    /**
     * Resolve nome do atendente associado à mensagem.
     */
    private function resolveAttendantName(ChatMessage $message): ?string
    {
        if (in_array($message->source, [ChatMessageDTO::SOURCE_BOT, 'ai'], true)) {
            $metadata = $message->metadata ?? [];
            $agentName = $metadata['ai_agent_name'] ?? null;
            if (is_string($agentName) && trim($agentName) !== '') {
                return trim($agentName);
            }

            return 'Assistente Virtual';
        }

        $message->loadMissing('user:id,name');
        $user = $message->user;

        if (! $user instanceof AuthUser) {
            return null;
        }

        $name = trim((string) $user->name);

        return $name !== '' ? $name : null;
    }

    /**
     * Resolver número de destino a partir do ticket.
     *
     * @param  ChatTicket  $ticket  Ticket para extração do número.
     * @return string|null Número limpo ou null.
     */
    private function resolveNumber(ChatTicket $ticket): ?string
    {
        $number = $ticket->phone_e164 ?? $ticket->phone ?? $ticket->remote_jid;
        if (! $number && $ticket->contact) {
            $number = $ticket->contact->whatsapp ?? $ticket->contact->phone;
        }

        if (! $number) {
            return null;
        }

        $number = str_contains((string) $number, '@') ? Str::before($number, '@') : $number;
        $cleaned = preg_replace('/[^0-9]/', '', (string) $number);

        return is_string($cleaned) && $cleaned !== '' ? $cleaned : null;
    }

    private function resolveInstanceToken(?ChatInstance $instance): ?string
    {
        if (! $instance instanceof ChatInstance) {
            return null;
        }

        if ($instance->provider === 'zapi') {
            $settings = $instance->settings_json ?? [];
            $instanceId = $settings['instance_id']
                ?? $settings['instance']
                ?? $settings['instanceId']
                ?? null;
            $tokenId = $settings['token_id']
                ?? $settings['token']
                ?? $settings['tokenId']
                ?? null;

            if ($instanceId && $tokenId) {
                return $instanceId.':'.$tokenId;
            }

            return null;
        }

        return $instance->webhook_token;
    }

    /**
     * Resolver payload do arquivo para envio ao gateway.
     *
     * @param  ChatMessage  $message  Mensagem com dados do arquivo.
     * @return array{file?: string, url?: string} Payload com base64 ou URL externa.
     */
    private function resolveFilePayload(ChatMessage $message): array
    {
        $fileUrl = $message->file_url;

        if (! $fileUrl) {
            return [];
        }

        $appUrl = (string) config('app.url', 'http://localhost');
        $isLocal = str_starts_with($fileUrl, $appUrl.'/storage/')
            || str_starts_with($fileUrl, '/storage/')
            || str_contains($fileUrl, 'localhost');

        if ($isLocal) {
            $storagePath = $this->extractStoragePath($fileUrl);

            if ($storagePath && Storage::disk('public')->exists($storagePath)) {
                $contents = Storage::disk('public')->get($storagePath);
                $detectedMime = Storage::disk('public')->mimeType($storagePath);
                $inferredMime = $this->inferMimeTypeFromPath($storagePath);
                $mimeType = $message->mime_type
                    ?? ($inferredMime !== 'application/octet-stream'
                        ? $inferredMime
                        : ($detectedMime !== false ? $detectedMime : 'application/octet-stream'));

                return [
                    'file' => 'data:'.$mimeType.';base64,'.base64_encode((string) $contents),
                ];
            }
        }

        return ['file' => $fileUrl];
    }

    /**
     * Extrair o caminho do storage a partir de uma URL.
     *
     * @param  string  $url  URL completa do arquivo.
     * @return string|null Caminho relativo no storage ou null.
     */
    private function extractStoragePath(string $url): ?string
    {
        if (str_contains($url, '/storage/')) {
            $path = Str::after($url, '/storage/');

            return $path !== '' ? $path : null;
        }

        return null;
    }

    private function inferMimeTypeFromPath(string $path): string
    {
        $extension = strtolower((string) pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'mp4' => 'video/mp4',
            'webm' => 'video/webm',
            'mp3' => 'audio/mpeg',
            'ogg' => 'audio/ogg',
            'wav' => 'audio/wav',
            'pdf' => 'application/pdf',
            default => 'application/octet-stream',
        };
    }

    /**
     * Resolver o tipo de mídia para o UAZAPI baseado no tipo da mensagem e MIME type.
     *
     * @param  ChatMessage  $message  Mensagem com metadados.
     * @return string Tipo de mídia (image, video, document, audio, ptt, sticker).
     */
    private function resolveMediaType(ChatMessage $message): string
    {
        $type = $message->type;
        $mimeType = strtolower((string) ($message->mime_type ?? ''));

        if (in_array($type, ['image', 'video', 'sticker', 'document'], true)) {
            return $type;
        }

        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        if (str_starts_with($mimeType, 'application/') || str_starts_with($mimeType, 'text/')) {
            return 'document';
        }

        if ($message->file_url) {
            return 'document';
        }

        return 'image';
    }
}
