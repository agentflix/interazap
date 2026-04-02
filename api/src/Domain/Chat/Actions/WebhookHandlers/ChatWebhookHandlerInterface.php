<?php

declare(strict_types=1);

namespace Domain\Chat\Actions\WebhookHandlers;

/**
 * Interface para handlers de eventos de webhook.
 */
interface ChatWebhookHandlerInterface
{
    /**
     * Determina se o handler suporta o evento/payload informado.
     *
     * @param  array<string, mixed>  $payload
     */
    public function supports(string $eventType, array $payload): bool;

    /**
     * Processa o evento.
     *
     * @param  array<string, mixed>  $payload
     */
    public function handle(string $tenantId, array $payload): void;
}
