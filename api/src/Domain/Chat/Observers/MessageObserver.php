<?php

declare(strict_types=1);

namespace Domain\Chat\Observers;

use Domain\Ai\Services\AutopilotRunSnapshotResolver;
use Domain\Chat\Models\ChatMessage;

final class MessageObserver
{
    public function created(ChatMessage $message): void
    {
        AutopilotRunSnapshotResolver::forgetForTicket(
            (string) $message->tenant_id,
            (string) $message->ticket_id,
        );
    }
}
