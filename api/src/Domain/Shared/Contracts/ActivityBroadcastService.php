<?php

declare(strict_types=1);

namespace Domain\Shared\Contracts;

/**
 * Contract for broadcasting activity updates across domains.
 */
interface ActivityBroadcastService
{
    /**
     * @param  array<string, mixed>  $contactData
     */
    public function emitContactUpdated(string $chatId, string $contactId, array $contactData): void;

    /**
     * @param  array<string, mixed>  $statusData
     */
    public function broadcastNegotiationStatusChanged(string $tenantId, string $contactId, array $statusData): void;
}
