<?php

declare(strict_types=1);

namespace Domain\Chat\Contracts;

/**
 * Interface for WhatsApp gateway services.
 *
 * Defines the contract for sending messages via WhatsApp API providers.
 */
interface ChatWhatsAppGatewayInterface
{
    /**
     * Send a message via WhatsApp.
     *
     * @param  string  $instanceId  The WhatsApp instance identifier.
     * @param  string  $to  The recipient phone number.
     * @param  string  $content  The message content.
     * @param  string  $type  The message type (text, image, document, etc.).
     * @param  array<string, mixed>  $metadata  Additional metadata.
     * @return array<string, mixed> The response from the provider.
     *
     * @throws \RuntimeException If the message cannot be sent.
     */
    public function sendMessage(
        string $instanceId,
        string $to,
        string $content,
        string $type = 'text',
        array $metadata = [],
    ): array;

    /**
     * Get the status of a sent message.
     *
     * @param  string  $instanceId  The WhatsApp instance identifier.
     * @param  string  $messageId  The provider message ID.
     * @return array<string, mixed> The message status.
     */
    public function getMessageStatus(string $instanceId, string $messageId): array;

    /**
     * Check if the instance is connected and ready.
     *
     * @param  string  $instanceId  The WhatsApp instance identifier.
     */
    public function isConnected(string $instanceId): bool;
}
