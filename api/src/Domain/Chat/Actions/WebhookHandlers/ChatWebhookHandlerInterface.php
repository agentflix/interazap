<?php

declare(strict_types=1);

namespace Domain\Chat\Actions\WebhookHandlers;

/**
 * Interface para handlers de eventos de webhook do chat.
 *
 * Cada implementação é responsável por um tipo específico de evento
 * (deleção, edição, reação) e é registrada para despacho pelo ChatWebhookIngestor.
 */
interface ChatWebhookHandlerInterface
{
    /**
     * Determina se este handler é capaz de processar o evento/payload informado.
     *
     * @param  string  $eventType  Tipo do evento recebido (ex.: 'message.delete').
     * @param  array<string, mixed>  $payload  Payload bruto do webhook.
     * @return bool True se este handler deve processar o evento.
     */
    public function supports(string $eventType, array $payload): bool;

    /**
     * Processa o evento de webhook.
     *
     * @param  string  $tenantId  Identificador do tenant.
     * @param  array<string, mixed>  $payload  Payload bruto do webhook.
     */
    public function handle(string $tenantId, array $payload): void;
}
