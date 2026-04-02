<?php

declare(strict_types=1);

namespace Domain\Ai\Observers;

use Domain\Ai\Models\AiAgentTrigger;
use Illuminate\Support\Facades\Cache;

/**
 * Keeps tenant trigger cache synchronized with trigger lifecycle changes.
 */
final class AiAgentTriggerObserver
{
    /**
     * Handle the AiAgentTrigger "saved" event.
     */
    public function saved(AiAgentTrigger $trigger): void
    {
        $this->rebuildTenantTriggerCache((string) $trigger->tenant_id);
    }

    /**
     * Handle the AiAgentTrigger "deleted" event.
     */
    public function deleted(AiAgentTrigger $trigger): void
    {
        $this->rebuildTenantTriggerCache((string) $trigger->tenant_id);
    }

    /**
     * Build cache key for active tenant triggers used by the dispatcher.
     */
    public static function cacheKey(string $tenantId): string
    {
        return sprintf('autopilot:triggers:tenant:%s', $tenantId);
    }

    private function rebuildTenantTriggerCache(string $tenantId): void
    {
        if ($tenantId === '') {
            return;
        }

        $cacheKey = self::cacheKey($tenantId);
        $triggers = AiAgentTrigger::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->with([
                'agent' => fn ($query) => $query
                    ->where('is_active', true)
                    ->with(['files' => fn ($filesQuery) => $filesQuery->orderBy('slug')]),
            ])
            ->get();

        Cache::put($cacheKey, $triggers, 3600);
    }
}
