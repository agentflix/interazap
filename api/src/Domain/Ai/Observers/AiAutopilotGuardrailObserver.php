<?php

declare(strict_types=1);

namespace Domain\Ai\Observers;

use Domain\Ai\Models\AiAutopilotGuardrail;
use Illuminate\Support\Facades\Cache;

final class AiAutopilotGuardrailObserver
{
    public function saved(AiAutopilotGuardrail $guardrail): void
    {
        $this->forgetTenantGuardrailsCache((string) $guardrail->tenant_id);
    }

    public function deleted(AiAutopilotGuardrail $guardrail): void
    {
        $this->forgetTenantGuardrailsCache((string) $guardrail->tenant_id);
    }

    private function forgetTenantGuardrailsCache(string $tenantId): void
    {
        if ($tenantId === '') {
            return;
        }

        Cache::forget("autopilot:guardrails:tenant:{$tenantId}");
    }
}
