<?php

declare(strict_types=1);

namespace Domain\Ai\Http\Controllers;

use Domain\Ai\Actions\Prompts\AssignTenantSegmentAction;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controller para atribuição de segmentos a tenants (SuperAdmin).
 */
final class AiTenantSegmentController extends BaseController
{
    /**
     * Atribui um segmento a um tenant.
     */
    public function update(
        Request $request,
        PlatformTenant $tenant,
        AssignTenantSegmentAction $action
    ): JsonResponse {
        $this->authorize('ai.autopilots.manage');

        $request->validate([
            'segment_id' => ['required', 'uuid', 'exists:ai_prompt_segments,id'],
        ]);

        $tenant = $action->execute($tenant, $request->input('segment_id'));

        return $this->success([
            'tenant_id' => $tenant->id,
            'segment_id' => $tenant->segment_id,
        ], 'Segment assigned to tenant successfully.');
    }
}
