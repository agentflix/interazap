<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\DTOs\ChatWebhookEventDTO;
use Domain\Chat\Jobs\ChatWebhookIngressJob;
use Domain\Chat\Models\ChatWebhookEvent;
use Domain\Chat\Repositories\ChatInstanceRepository;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Casos de Uso para Processamento de Webhooks Telegram.
 *
 * Responsável por receber payloads da Telegram Bot API (via Gateway),
 * normalizar para o formato padrão do ingestor, evitar duplicidade
 * (idempotência via update_id), persistir e disparar o fluxo de ingestão.
 *
 * @category Actions
 */
final class ChatTelegramWebhookActions
{
    public function __construct(
        private readonly ChatInstanceRepository $instances,
    ) {}

    /**
     * Ponto de entrada para processamento de um webhook Telegram.
     *
     * Resolve a instância, normaliza o payload Telegram para o formato
     * padrão do ingestor, aplica idempotência e despacha para processamento.
     *
     * @param  string  $token  Token de autenticação do webhook/instância.
     * @param  array<string, mixed>  $payload  Dados do update Telegram recebidos do Gateway.
     * @return array<string, mixed> Resultado da operação.
     *
     * @throws RuntimeException Se o token for inválido.
     */
    public function handle(string $token, array $payload): array
    {
        $instance = $this->instances->resolveInstanceByToken($token);
        if (! $instance) {
            $inactive = $this->instances->findByWebhookToken($token);
            if ($inactive) {
                logger()->warning('[ChatTelegramWebhook] Webhook ignorado: instância inativa', [
                    'token' => $this->maskToken($token),
                    'instance_id' => (string) $inactive->id,
                    'tenant_id' => (string) $inactive->tenant_id,
                ]);

                return ['success' => true, 'ignored' => true, 'reason' => 'inactive'];
            }

            throw new RuntimeException('Token de webhook inválido');
        }

        $tenantId = (string) $instance->tenant_id;
        $isEdited = isset($payload['edited_message']);
        $telegramMessage = $isEdited
            ? ($payload['edited_message'] ?? [])
            : ($payload['message'] ?? []);

        if ($telegramMessage === []) {
            logger()->debug('[ChatTelegramWebhook] Update sem message/edited_message, ignorando', [
                'update_id' => $payload['update_id'] ?? null,
                'payload_keys' => array_keys($payload),
            ]);

            return ['success' => true, 'ignored' => true, 'reason' => 'unsupported_update_type'];
        }

        $normalized = $this->normalizeTelegramPayload($telegramMessage, $isEdited, $payload);
        $normalized['provider'] = 'telegram';
        $normalized['instance_webhook_token'] = $token;
        $normalized['tenant_id'] = $tenantId;
        $normalized['instance_id'] = (string) $instance->id;
        $normalized['event_type'] = $isEdited ? 'messages_update' : 'messages';
        $normalized['direction'] = 'incoming';

        $dto = ChatWebhookEventDTO::fromNormalized($normalized);
        $payloadArray = $dto->toArray();

        $descriptor = $this->buildIdempotencyDescriptor($payloadArray, $payload);

        if (! $this->acquireIdempotency($descriptor['key'])) {
            logger()->debug('[ChatTelegramWebhook] Duplicate skipped', ['idempotency_key' => $descriptor['key']]);

            return ['success' => true, 'duplicate' => true];
        }

        $streamId = (string) ($telegramMessage['message_id'] ?? $descriptor['key']);
        if (strlen($streamId) > 255) {
            $streamId = hash('sha256', $streamId);
        }

        $eventId = (string) Str::orderedUuid();
        $payloadJson = json_encode($payloadArray, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $inserted = ChatWebhookEvent::query()->insertOrIgnore([
            'id' => $eventId,
            'tenant_id' => $tenantId,
            'domain' => 'chat',
            'stream_id' => $streamId,
            'idempotency_key' => $descriptor['key'],
            'provider' => 'telegram',
            'instance_webhook_token' => $token,
            'event_type' => $payloadArray['event_type'] ?? null,
            'direction' => $payloadArray['direction'] ?? null,
            'payload' => $payloadJson,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        if ($inserted === 0) {
            logger()->debug('[ChatTelegramWebhook] Duplicate skipped (db)', [
                'idempotency_key' => $descriptor['key'],
                'stream_id' => $streamId,
            ]);

            return ['success' => true, 'duplicate' => true];
        }

        ChatWebhookIngressJob::dispatch($tenantId, $payloadArray, $descriptor);

        return [
            'success' => true,
            'event_id' => $eventId,
            'idempotency_key' => $descriptor['key'],
        ];
    }

    /**
     * Normaliza o payload Telegram para o formato esperado pelo ChatWebhookIngestor.
     *
     * Mapeia campos Telegram (chat.id, from.first_name, message_id, etc.)
     * para a estrutura normalizada (remote_jid, message.id, message.body, etc.).
     *
     * @param  array<string, mixed>  $telegramMessage  O objeto message ou edited_message.
     * @param  bool  $isEdited  Se é uma mensagem editada.
     * @param  array<string, mixed>  $rawPayload  Payload Telegram completo (inclui update_id).
     * @return array<string, mixed> Payload normalizado.
     */
    private function normalizeTelegramPayload(array $telegramMessage, bool $isEdited, array $rawPayload): array
    {
        $chat = is_array($telegramMessage['chat'] ?? null) ? $telegramMessage['chat'] : [];
        $from = is_array($telegramMessage['from'] ?? null) ? $telegramMessage['from'] : [];
        $chatId = (string) ($chat['id'] ?? '');
        $messageId = (string) ($telegramMessage['message_id'] ?? '');

        $messageType = $this->resolveMessageType($telegramMessage);
        $fileId = $this->extractFileId($telegramMessage, $messageType);
        $body = $telegramMessage['text']
            ?? $telegramMessage['caption']
            ?? '';

        $senderName = trim(
            ($from['first_name'] ?? '').' '.($from['last_name'] ?? '')
        );
        $senderUsername = $from['username'] ?? null;

        $replyToMessageId = null;
        if (isset($telegramMessage['reply_to_message']['message_id'])) {
            $replyToMessageId = (string) $telegramMessage['reply_to_message']['message_id'];
        }

        return [
            'remote_jid' => $chatId,
            'message' => [
                'id' => $messageId,
                'chatid' => $chatId,
                'body' => (string) $body,
                'type' => $messageType,
                'fromMe' => false,
                'timestamp' => $telegramMessage['date'] ?? null,
            ],
            'chat' => [
                'id' => $chatId,
                'name' => $chat['title'] ?? $senderName,
                'type' => $chat['type'] ?? 'private',
            ],
            'raw' => [
                'telegram' => $rawPayload,
                'message' => $telegramMessage,
            ],
            'metadata' => [
                'telegram_update_id' => $rawPayload['update_id'] ?? null,
                'telegram_message_id' => (int) $messageId,
                'telegram_chat_id' => $chatId,
                'telegram_file_id' => $fileId,
                'telegram_sender_username' => $senderUsername,
                'telegram_sender_name' => $senderName,
                'is_edited' => $isEdited,
                'edit_date' => $isEdited ? ($telegramMessage['edit_date'] ?? null) : null,
                'reply_to_message_id' => $replyToMessageId,
            ],
        ];
    }

    /**
     * Resolve o tipo de mensagem Telegram para o formato normalizado.
     *
     * @param  array<string, mixed>  $message  Objeto message do Telegram.
     * @return string Tipo normalizado (text, file, audio, location, contact).
     */
    private function resolveMessageType(array $message): string
    {
        if (isset($message['photo'])) {
            return 'file';
        }
        if (isset($message['video'])) {
            return 'file';
        }
        if (isset($message['document'])) {
            return 'file';
        }
        if (isset($message['sticker'])) {
            return 'file';
        }
        if (isset($message['voice'])) {
            return 'audio';
        }
        if (isset($message['audio'])) {
            return 'audio';
        }
        if (isset($message['video_note'])) {
            return 'file';
        }
        if (isset($message['location'])) {
            return 'location';
        }
        if (isset($message['contact'])) {
            return 'contact';
        }

        return 'text';
    }

    /**
     * Extrai o file_id da mensagem Telegram conforme o tipo de mídia.
     *
     * Para photos, usa o último elemento (maior resolução).
     *
     * @param  array<string, mixed>  $message  Objeto message do Telegram.
     * @param  string  $type  Tipo de mensagem normalizado.
     * @return string|null file_id ou null se não houver mídia.
     */
    private function extractFileId(array $message, string $type): ?string
    {
        if (isset($message['photo']) && is_array($message['photo'])) {
            $photos = $message['photo'];
            $largest = end($photos);

            return is_array($largest) ? ($largest['file_id'] ?? null) : null;
        }

        $mediaKeys = ['video', 'document', 'sticker', 'voice', 'audio', 'video_note'];
        foreach ($mediaKeys as $key) {
            if (isset($message[$key]) && is_array($message[$key])) {
                return $message[$key]['file_id'] ?? null;
            }
        }

        return null;
    }

    /**
     * Constrói descritor de idempotência baseado no update_id do Telegram.
     *
     * @param  array<string, mixed>  $normalizedPayload  Payload normalizado.
     * @param  array<string, mixed>  $rawPayload  Payload Telegram original.
     * @return array{key:string,provider:string,eventType:string,normalizedEventType:string,token:string,discriminator:string|null,message_id:string|null}
     */
    private function buildIdempotencyDescriptor(array $normalizedPayload, array $rawPayload): array
    {
        $eventType = (string) ($normalizedPayload['event_type'] ?? 'messages');
        $token = (string) ($normalizedPayload['instance_webhook_token'] ?? '');
        $updateId = (string) ($rawPayload['update_id'] ?? '');
        $messageId = Arr::get($normalizedPayload, 'message.id');

        $discriminator = $updateId !== '' ? $updateId : ((string) $messageId ?: 'generic');

        $rawKey = sprintf('idempo:telegram:%s:%s:%s', $eventType, $token, $discriminator);
        $key = strlen($rawKey) > 255 ? 'idempo_hash:'.hash('sha256', $rawKey) : $rawKey;

        return [
            'key' => $key,
            'provider' => 'telegram',
            'eventType' => $eventType,
            'normalizedEventType' => $eventType,
            'token' => $token,
            'discriminator' => $discriminator,
            'message_id' => $messageId ? (string) $messageId : null,
        ];
    }

    /**
     * Tenta adquirir trava de idempotência no cache.
     *
     * @param  string  $key  Chave de idempotência.
     * @return bool True se adquirida (primeiro processamento).
     */
    private function acquireIdempotency(string $key): bool
    {
        return Cache::add($key, true, 600);
    }

    /**
     * Mascara o token para logs.
     */
    private function maskToken(string $token): string
    {
        $suffix = substr($token, -8);

        return $suffix ? '****-'.$suffix : '****';
    }
}
