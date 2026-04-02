<?php

declare(strict_types=1);

namespace Domain\Ai\Listeners;

use Domain\Ai\Enums\AutopilotTriggerType;
use Domain\Ai\Events\AiRunRequested;
use Domain\Ai\Events\AutopilotTriggerFired;

/**
 * Gatekeeper listener for the AI Autopilot pipeline.
 *
 * Translates an AiRunRequested event (Chat domain signal) into
 * AutopilotTriggerFired (Ai domain), maintaining clean domain separation.
 *
 * Business trigger conditions (ALL must be true before this listener fires):
 *   1. Inbound message body is not empty — enforced in ChatWebhookRouter.
 *   2. Ticket is NOT under human takeover — enforced in ChatWebhookRouter.
 *   3. Tenant plan has AI enabled — enforced via PlatformPlanEnforcementService.
 *
 * Downstream idempotency (duplicate-message guard) is enforced in
 * AutopilotRunDispatcherListener by checking for existing queued/running runs
 * with the same message_id.
 *
 * @category Listeners
 */
final class AiGateKeeperListener
{
    /**
     * Handle the AiRunRequested event.
     */
    public function handle(AiRunRequested $event): void
    {
        AutopilotTriggerFired::dispatch(
            $event->tenantId,
            AutopilotTriggerType::INBOUND_MESSAGE,
            [
                'ticket_id' => $event->ticketId,
                'message_id' => (string) ($event->context['message_id'] ?? ''),
                'body' => $event->body,
                'instance_id' => (string) ($event->context['instance_id'] ?? ''),
                'source_type' => 'ticket',
            ],
            $event->ticketId,
        );
    }
}
