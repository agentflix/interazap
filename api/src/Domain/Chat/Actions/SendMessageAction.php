<?php

declare(strict_types=1);

namespace Domain\Chat\Actions;

use Domain\Chat\DTOs\ChatMessageDTO;
use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Action para enviar mensagens com idempotência.
 *
 * Garante que mensagens não sejam enviadas em duplicidade
 * utilizando cache com chave baseada em ticket_id + content_hash + timestamp.
 */
final class SendMessageAction
{
    /**
     * Cache TTL in seconds (1 hour).
     */
    private const IDEMPOTENCY_TTL = 3600;

    /**
     * Cache prefix for idempotency keys.
     */
    private const CACHE_PREFIX = 'message_idempotent';

    /**
     * Time window in seconds for duplicate detection.
     */
    private const DEDUP_WINDOW_SECONDS = 5;

    public function __construct(
        private readonly SendChatMessageAction $messageActions,
    ) {}

    /**
     * Envia uma mensagem com verificação de idempotência.
     *
     * @param  string  $tenantId  ID do tenant
     * @param  ChatMessageDTO  $dto  Dados da mensagem
     * @param  bool  $skipIdempotency  Pular verificação (para testes)
     * @return array{created: bool, message: ChatMessage|null, reason: string|null}
     */
    public function handle(
        string $tenantId,
        ChatMessageDTO $dto,
        bool $skipIdempotency = false
    ): array {
        $idempotencyKey = $this->generateIdempotencyKey($dto);

        // Verificar se já foi processado
        if (! $skipIdempotency && $this->hasBeenProcessed($idempotencyKey)) {
            $cachedMessageId = $this->getCachedMessageId($idempotencyKey);

            Log::info('[SendMessageAction] Mensagem duplicada detectada', [
                'tenant_id' => $tenantId,
                'ticket_id' => $dto->ticketId,
                'idempotency_key' => $idempotencyKey,
                'cached_message_id' => $cachedMessageId,
            ]);

            $existingMessage = $cachedMessageId
                ? ChatMessage::find($cachedMessageId)
                : null;

            return [
                'created' => false,
                'message' => $existingMessage,
                'reason' => 'duplicate_message',
            ];
        }

        // Verificar se o ticket existe
        $ticket = ChatTicket::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $dto->ticketId)
            ->first();

        if (! $ticket) {
            Log::warning('[SendMessageAction] Ticket não encontrado', [
                'tenant_id' => $tenantId,
                'ticket_id' => $dto->ticketId,
            ]);

            return [
                'created' => false,
                'message' => null,
                'reason' => 'ticket_not_found',
            ];
        }

        // Verificar duplicata recente no banco (janela de tempo)
        if (! $skipIdempotency && $this->hasDuplicateInDatabase($tenantId, $dto)) {
            Log::info('[SendMessageAction] Mensagem duplicada no banco', [
                'tenant_id' => $tenantId,
                'ticket_id' => $dto->ticketId,
            ]);

            return [
                'created' => false,
                'message' => null,
                'reason' => 'duplicate_in_database',
            ];
        }

        // Criar mensagem usando ChatMessageActions
        $message = $this->messageActions->create($tenantId, $dto);

        // Marcar como processado no cache
        $this->markAsProcessed($idempotencyKey, $message->id);

        Log::info('[SendMessageAction] Mensagem enviada com sucesso', [
            'tenant_id' => $tenantId,
            'message_id' => $message->id,
            'ticket_id' => $dto->ticketId,
        ]);

        return [
            'created' => true,
            'message' => $message,
            'reason' => null,
        ];
    }

    /**
     * Gera chave de idempotência única para a mensagem.
     *
     * Baseada em: ticket_id + content_hash + timestamp_bucket
     */
    private function generateIdempotencyKey(ChatMessageDTO $dto): string
    {
        // Usar bucket de tempo para agrupar mensagens similares
        $timestampBucket = (int) floor(time() / self::DEDUP_WINDOW_SECONDS);
        $contentHash = $this->hashContent($dto->content);

        $payload = sprintf(
            '%s:%s:%d',
            $dto->ticketId,
            $contentHash,
            $timestampBucket
        );

        return sprintf('%s:%s', self::CACHE_PREFIX, md5($payload));
    }

    /**
     * Gera hash do conteúdo da mensagem.
     */
    private function hashContent(string $content): string
    {
        // Normalizar conteúdo antes do hash
        $normalized = mb_strtolower(trim($content));

        return md5($normalized);
    }

    /**
     * Verifica se a mensagem já foi processada.
     */
    private function hasBeenProcessed(string $key): bool
    {
        return Cache::has($key);
    }

    /**
     * Obtém o ID da mensagem cacheada.
     */
    private function getCachedMessageId(string $key): ?string
    {
        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($key);

        if (! is_array($cached) || ! isset($cached['message_id'])) {
            return null;
        }

        return is_string($cached['message_id']) ? $cached['message_id'] : null;
    }

    /**
     * Marca a mensagem como processada no cache.
     */
    private function markAsProcessed(string $key, string $messageId): void
    {
        Cache::put($key, [
            'message_id' => $messageId,
            'processed_at' => now()->toIso8601String(),
        ], self::IDEMPOTENCY_TTL);
    }

    /**
     * Verifica se existe mensagem duplicada recente no banco.
     */
    private function hasDuplicateInDatabase(string $tenantId, ChatMessageDTO $dto): bool
    {
        $contentHash = $this->hashContent($dto->content);
        $windowStart = now()->subSeconds(self::DEDUP_WINDOW_SECONDS);

        return ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->where('ticket_id', $dto->ticketId)
            ->where('direction', $dto->direction)
            ->where('created_at', '>=', $windowStart)
            ->whereContentHash($contentHash)
            ->exists();
    }

    /**
     * Limpa a chave de idempotência (para testes ou retry manual).
     */
    public function clearIdempotencyKey(ChatMessageDTO $dto): void
    {
        $key = $this->generateIdempotencyKey($dto);
        Cache::forget($key);

        Log::info('[SendMessageAction] Chave de idempotência limpa', [
            'ticket_id' => $dto->ticketId,
        ]);
    }

    /**
     * Gera uma chave de idempotência a partir de um external_id (webhook).
     *
     * Útil para webhooks onde o provider envia um ID único.
     */
    public static function keyFromExternalId(string $ticketId, string $externalId): string
    {
        return sprintf('%s:external:%s:%s', self::CACHE_PREFIX, $ticketId, $externalId);
    }

    /**
     * Verifica idempotência por external_id.
     */
    public function hasProcessedByExternalId(string $ticketId, string $externalId): bool
    {
        $key = self::keyFromExternalId($ticketId, $externalId);

        return Cache::has($key);
    }

    /**
     * Marca como processado por external_id.
     */
    public function markProcessedByExternalId(string $ticketId, string $externalId, string $messageId): void
    {
        $key = self::keyFromExternalId($ticketId, $externalId);

        Cache::put($key, [
            'message_id' => $messageId,
            'processed_at' => now()->toIso8601String(),
        ], self::IDEMPOTENCY_TTL);
    }
}
