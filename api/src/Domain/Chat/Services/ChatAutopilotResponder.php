<?php

declare(strict_types=1);

namespace Domain\Chat\Services;

use Domain\Ai\Events\AiRunRequested;

/**
 * Asynchronous responder for Autopilot replies.
 *
 * Resolves the playbook automatically from the tenant's active playbooks.
 * No instance-level configuration is required.
 */
final class ChatAutopilotResponder
{
    /**
     * Dispatches an async job to run Autopilot and respond.
     *
     * @param  string  $tenantId  Tenant identifier.
     * @param  string  $ticketId  Ticket identifier.
     * @param  string  $body  Incoming message content.
     * @param  array<string, mixed>  $context  Context data (instance_id, message_id, etc.).
     */
    public function respond(string $tenantId, string $ticketId, string $body, array $context = []): void
    {
        AiRunRequested::dispatch($tenantId, $ticketId, $body, $context);
    }
}
