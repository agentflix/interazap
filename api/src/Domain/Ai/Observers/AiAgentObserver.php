<?php

declare(strict_types=1);

namespace Domain\Ai\Observers;

use Domain\Ai\Models\AiAgent;
use Domain\Ai\Services\AutopilotRunSnapshotResolver;

final class AiAgentObserver
{
    public function saved(AiAgent $agent): void
    {
        AutopilotRunSnapshotResolver::forgetForAgent(
            (string) $agent->tenant_id,
            (string) $agent->id,
        );
    }
}
