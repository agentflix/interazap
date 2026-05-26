<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Controllers;

use Domain\Billing\Http\Requests\BillingPrefsUpdateRequest;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Http\Controllers\BaseController;
use Domain\Shared\Services\AuditLogger;
use Illuminate\Http\JsonResponse;

/**
 * Controller para preferências de cobrança do tenant.
 *
 * Permite que o tenant substitua o modo de excedente definido no plano pela sua conta.
 */
final class BillingPrefsController extends BaseController
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * Atualiza as preferências de cobrança do tenant (modo de excedente).
     *
     * @param  BillingPrefsUpdateRequest  $request  Payload com overage_mode_override
     * @return JsonResponse `{overage_mode_override}` atualizado
     */
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
