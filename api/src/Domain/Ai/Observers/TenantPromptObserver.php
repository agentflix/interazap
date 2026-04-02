<?php

declare(strict_types=1);

namespace Domain\Ai\Observers;

use Domain\Ai\Models\AiPromptSegment;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Observer para PlatformTenant que atribui o segmento GENERAL automaticamente.
 *
 * Todo novo tenant deve ter um segment_id atribuído. Se não for especificado,
 * o segmento GENERAL é atribuído como fallback.
 */
class TenantPromptObserver
{
    /**
     * Handle the PlatformTenant "created" event.
     */
    public function created(PlatformTenant $tenant): void
    {
        if ($tenant->segment_id !== null) {
            return;
        }

        if (! Schema::hasTable('ai_prompt_segments')) {
            Log::warning('TenantPromptObserver: table ai_prompt_segments not found. Skipping assignment.');

            return;
        }

        $generalSegment = AiPromptSegment::getGeneral();

        if (! $generalSegment) {
            Log::warning('TenantPromptObserver: GENERAL segment not found. Run the seeder first.');

            return;
        }

        $tenant->forceFill([
            'segment_id' => $generalSegment->id,
        ])->save();

        Log::info('TenantPromptObserver: Assigned GENERAL segment to tenant', [
            'tenant_id' => $tenant->id,
            'segment_id' => $generalSegment->id,
        ]);
    }
}
