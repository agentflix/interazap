<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Controllers;

use Domain\Billing\Actions\BillingUnlockTenantAction;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Enums\BillingTenantStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Http\Controllers\BaseController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Endpoints administrativos para gestão de inadimplência.
 */
final class BillingAdminDelinquencyController extends BaseController
{
    public function __construct(
        private readonly BillingUnlockTenantAction $unlockTenantAction,
    ) {}

    /**
     * Lista tenants inadimplentes com dias de atraso.
     */
    public function index(Request $request): JsonResponse
    {
        if (! $request->user()?->isSuperAdmin()) {
            return $this->forbidden('Apenas super-admin pode acessar este endpoint.');
        }

        $rows = BillingInvoice::query()
            ->withoutGlobalScopes()
            ->join('platform_tenants', 'platform_tenants.id', '=', 'billing_invoices.tenant_id')
            ->where('billing_invoices.status', BillingInvoiceStatus::OVERDUE->value)
            ->whereIn('platform_tenants.billing_status', [
                BillingTenantStatus::GRACE->value,
                BillingTenantStatus::LOCKED->value,
                BillingTenantStatus::PENDING_PURGE->value,
            ])
            ->selectRaw('platform_tenants.id as tenant_id, platform_tenants.name as tenant_name, platform_tenants.billing_status, MIN(billing_invoices.due_date) as oldest_due_date, COUNT(billing_invoices.id) as overdue_invoices')
            ->groupBy('platform_tenants.id', 'platform_tenants.name', 'platform_tenants.billing_status')
            ->orderByRaw('MIN(billing_invoices.due_date) asc')
            ->get()
            ->map(static function (object $row): array {
                $oldestDueDate = Carbon::parse((string) $row->oldest_due_date)->startOfDay();

                return [
                    'tenant_id' => (string) $row->tenant_id,
                    'tenant_name' => (string) $row->tenant_name,
                    'billing_status' => (string) $row->billing_status,
                    'days_overdue' => max(0, $oldestDueDate->diffInDays(now()->startOfDay())),
                    'overdue_invoices' => (int) $row->overdue_invoices,
                    'oldest_due_date' => (string) $row->oldest_due_date,
                ];
            })
            ->values()
            ->all();

        return $this->success($rows, 'Tenants inadimplentes listados com sucesso.');
    }

    /**
     * Desbloqueia tenant manualmente.
     */
    public function unlock(Request $request, string $tenantId): JsonResponse
    {
        if (! $request->user()?->isSuperAdmin()) {
            return $this->forbidden('Apenas super-admin pode desbloquear tenant.');
        }

        $tenant = PlatformTenant::query()
            ->withoutGlobalScopes()
            ->find($tenantId);

        if (! $tenant instanceof PlatformTenant) {
            return $this->notFound('Tenant não encontrado.');
        }

        $this->unlockTenantAction->handle($tenant);

        return $this->success([
            'tenant_id' => (string) $tenant->id,
            'billing_status' => BillingTenantStatus::ACTIVE->value,
        ], 'Tenant desbloqueado com sucesso.');
    }
}
