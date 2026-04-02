<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Platform\Models\PlatformTenant;

/**
 * Action para atribuir segmento a um tenant.
 */
final class AssignTenantSegmentAction
{
    public function execute(PlatformTenant $tenant, string $segmentId): PlatformTenant
    {
        $segment = \Domain\Ai\Models\AiPromptSegment::query()->findOrFail($segmentId);

        $tenant->forceFill([
            'segment_id' => $segment->id,
        ])->save();

        return $tenant->refresh();
    }
}
