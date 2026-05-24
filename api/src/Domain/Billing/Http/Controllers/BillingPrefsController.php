<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Controllers;

use Domain\Billing\Http\Requests\BillingPrefsUpdateRequest;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Services\AuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * Controller for tenant billing preferences.
 *
 * Allows tenants to override the plan-level overage mode for their account.
 */
final class BillingPrefsController extends BaseController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    public function update(BillingPrefsUpdateRequest $request): JsonResponse
    {
        $tenantId = $this->tenantId($request);

        $tenant = PlatformTenant::query()
            ->withoutGlobalScopes()
            ->findOrFail($tenantId);

        $override = $request->validated('overage_mode_override');

        $tenant->overage_mode_override = $override;
        $tenant->save();

        $this->auditLogger->log($request->user(), $tenantId, 'billing.prefs.updated', $tenant);

        return $this->success([
            'overage_mode_override' => $tenant->overage_mode_override,
        ], 'Preferências de cobrança atualizadas');
    }
}
