<?php

declare(strict_types=1);

namespace Domain\Ai\Actions\Prompts;

use Domain\Platform\Models\PlatformTenant;

/**
 * Action para atribuir um segmento de prompt a um tenant, afetando a resolução de prompts.
 *
 * O segmento define instruções de nicho/mercado que são herdadas pelo tenant
 * na cadeia de resolução MASTER > SEGMENT > PLAN > TENANT.
 */
final class AssignTenantSegmentAction
{
    /**
     * Atribui um segmento de prompt a um tenant.
     *
     * @param  PlatformTenant  $tenant  Tenant a atualizar.
     * @param  string  $segmentId  UUID do segmento a atribuir.
     * @return PlatformTenant Tenant atualizado com o novo segment_id.
     *
     * @throws \Illuminate\Database\Eloquent\ModelNotFoundException Se o segmento não existir.
     */
    public function execute(PlatformTenant $tenant, string $segmentId): PlatformTenant
    {
        $segment = \Domain\Ai\Models\AiPromptSegment::query()->findOrFail($segmentId);

        $tenant->forceFill([
            'segment_id' => $segment->id,
        ])->save();

        return $tenant->refresh();
    }
}
