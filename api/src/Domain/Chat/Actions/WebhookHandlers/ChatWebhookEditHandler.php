<?php

declare(strict_types=1);

namespace Domain\Chat\Actions\WebhookHandlers;

use Domain\Chat\Models\ChatMessage;
use Domain\Chat\Services\ChatBroadcastService;

/**
 * Handler para eventos de edição de mensagens via webhook.
 *
 * Resolve referências de múltiplos candidatos de ID, aplica o novo conteúdo
 * mantendo histórico de edições e emite evento WebSocket de edição.
 * Ignora duplicatas (mesmo conteúdo).
 */
final class ChatWebhookEditHandler implements ChatWebhookHandlerInterface
{
    public function __construct(private readonly ChatBroadcastService $broadcastService) {}

    /**
     * Suporta eventos 'message.edit' e 'messages.edit'.
     *
     * @param  string  $eventType  Tipo do evento recebido.
     * @param  array<string, mixed>  $payload  Payload bruto do webhook.
     * @return bool True para eventos de edição.
     */
    public function supports(string $eventType, array $payload): bool
    {
        return $eventType === 'message.edit' || $eventType === 'messages.edit';
    }

    /**
     * Processa o evento de edição, atualizando o conteúdo da mensagem e emitindo broadcast.
     *
     * Opera silenciosamente se não encontrar referências de ID ou mensagem no banco.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<string, mixed>  $payload  Payload bruto do evento de edição.
     */
    public function handle(string $tenantId, array $payload): void
    {
        $messageReferences = $this->resolveMessageReferences($payload);

        $newContent = data_get($payload, 'message.body')
            ?? data_get($payload, 'raw.message.editedMessage.message.conversation')
            ?? data_get($payload, 'raw.message.conversation')
            ?? data_get($payload, 'content');

        if ($messageReferences === []) {
            logger()->warning('[ChatWebhookEditHandler] Edit event without message ID', $payload);

            return;
        }

        $messages = ChatMessage::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('external_id', $messageReferences)
            ->get()
            ->keyBy('external_id');

        $message = null;

        foreach ($messageReferences as $reference) {
            $candidate = $messages->get($reference);

            if ($candidate instanceof ChatMessage) {
                $message = $candidate;

                break;
            }
        }

        if (! $message) {
            logger()->debug('[ChatWebhookEditHandler] Message not found for edit', [
                'external_ids' => $messageReferences,
            ]);

            return;
        }

        if (! $this->applyEdit($message, $newContent)) {
            logger()->debug('[ChatWebhookEditHandler] Duplicate edit ignored', ['message_id' => $message->id]);

            return;
        }

        $message->loadMissing('extended');
        $message->save();

        // Broadcast da edição
        $this->broadcastService->emitMessageEdit([
            'message_id' => (string) $message->id,
            'ticket_id' => (string) $message->ticket_id,
            'tenant_id' => $tenantId,
            'content' => $message->content,
            'is_edited' => true,
            'edited_at' => $message->edited_at?->toIso8601String(),
        ]);

        logger()->debug('[ChatWebhookEditHandler] Message edited', ['message_id' => $message->id]);
    }

    /**
     * Aplica a edição à mensagem, preservando histórico.
     *
     * Retorna false se o conteúdo novo for igual ao atual (duplicata ignorada).
     *
     * @param  ChatMessage  $message  Mensagem a editar.
     * @param  mixed  $newContent  Novo conteúdo recebido no payload.
     * @return bool True se a edição foi aplicada, false se ignorada.
     */
    private function applyEdit(ChatMessage $message, mixed $newContent): bool
    {
        $editedAt = now();
        $resolvedContent = is_string($newContent) ? $newContent : $message->content;

        if ($resolvedContent === $message->content) {
            return false;
        }

        $editHistory = is_array($message->edit_history) ? $message->edit_history : [];

        if ($resolvedContent !== $message->content) {
            $editHistory[] = [
                'content' => $message->content,
                'edited_at' => $editedAt->toIso8601String(),
            ];

            $message->content = $resolvedContent;
        }

        $message->is_edited = true;
        $message->edited_at = $editedAt;
        $message->edit_history = $editHistory;

        return true;
    }

    /**
     * Resolve as referências de ID da mensagem a editar a partir do payload.
     *
     * Tenta primeiro os campos explícitos de edição (message.edited, raw.message.edited)
     * e cai para fallbacks (message_id, raw.key.id, etc.) quando não encontrados.
     *
     * @param  array<string, mixed>  $payload  Payload bruto do evento.
     * @return list<string> Lista de external_ids candidatos (deduplicados).
     */
    private function resolveMessageReferences(array $payload): array
    {
        $explicitEditCandidates = [
            data_get($payload, 'message.edited'),
            data_get($payload, 'raw.message.edited'),
        ];

        $explicitReferences = $this->normalizeReferences($explicitEditCandidates);

        if ($explicitReferences !== []) {
            return $explicitReferences;
        }

        $fallbackCandidates = [
            data_get($payload, 'message_id'),
            data_get($payload, 'raw.key.id'),
            data_get($payload, 'message.messageid'),
            data_get($payload, 'message.messageId'),
            data_get($payload, 'raw.message.messageid'),
            data_get($payload, 'raw.message.messageId'),
            data_get($payload, 'raw.message.id'),
            data_get($payload, 'message.id'),
        ];

        return $this->normalizeReferences($fallbackCandidates);
    }

    /**
     * Normaliza candidatos a referência, filtrando strings válidas e deduplicando.
     *
     * @param  list<mixed>  $candidates  Valores candidatos (podem ser null ou não-string).
     * @return list<string> Referências normalizadas e deduplicadas.
     */
    private function normalizeReferences(array $candidates): array
    {
        $references = [];

        foreach ($candidates as $candidate) {
            if (! is_string($candidate)) {
                continue;
            }

            $reference = trim($candidate);

            if ($reference === '' || in_array($reference, $references, true)) {
                continue;
            }

            $references[] = $reference;
        }

        return $references;
    }
}
