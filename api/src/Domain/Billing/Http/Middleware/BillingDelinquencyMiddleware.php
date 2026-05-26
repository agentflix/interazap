<?php

declare(strict_types=1);

namespace Domain\Billing\Http\Middleware;

use Closure;
use Domain\Billing\Enums\BillingTenantStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia acesso de tenants inadimplentes fora da whitelist de cobrança.
 */
final class BillingDelinquencyMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isFeatureEnabled()) {
            return $next($request);
        }

        $tenantId = $request->user()?->tenant_id;
        if (! is_string($tenantId) || $tenantId === '') {
            return $next($request);
        }

        $billingState = $this->resolveBillingState($tenantId);
        if ($billingState === null) {
            return $next($request);
        }

        if (! in_array($billingState['billing_status'], [
            BillingTenantStatus::LOCKED->value,
            BillingTenantStatus::PENDING_PURGE->value,
            BillingTenantStatus::PURGED->value,
        ], true)) {
            return $next($request);
        }

        if ($this->isWhitelistedRoute($request)) {
            return $next($request);
        }

        return $this->lockedResponse($tenantId, $billingState);
    }

    /** Verifica se o módulo de inadimplência está habilitado pela config. */
    private function isFeatureEnabled(): bool
    {
        return (bool) config('billing.delinquency.enabled', true);
    }

    /**
     * @return array{billing_status:string,billing_locked_at:string|null,billing_purge_deadline:string|null}|null
     */
    private function resolveBillingState(string $tenantId): ?array
    {
        $prefix = (string) config('billing.delinquency.cache.billing_status_prefix', 'billing:tenant_status:');
        $ttl = (int) config('billing.delinquency.cache.billing_status_ttl', 300);

        /** @var array{billing_status:string,billing_locked_at:string|null,billing_purge_deadline:string|null}|null $state */
        $state = Cache::remember($prefix.$tenantId, $ttl, static function () use ($tenantId): ?array {
            if (! Schema::hasColumns('platform_tenants', ['billing_status', 'billing_locked_at', 'billing_purge_deadline'])) {
                return null;
            }

            $tenant = PlatformTenant::query()
                ->withoutGlobalScopes()
                ->select(['id', 'billing_status', 'billing_locked_at', 'billing_purge_deadline'])
                ->find($tenantId);

            if (! $tenant instanceof PlatformTenant) {
                return null;
            }

            return [
                'billing_status' => (string) $tenant->billing_status->value,
                'billing_locked_at' => $tenant->billing_locked_at?->toIso8601String(),
                'billing_purge_deadline' => $tenant->billing_purge_deadline?->toDateString(),
            ];
        });

        return $state;
    }

    /** Verifica se a rota atual está na lista de permissões para tenants bloqueados. */
    private function isWhitelistedRoute(Request $request): bool
    {
        $allowedRoutes = [
            ['method' => 'GET', 'pattern' => 'api/billing/invoices'],
            ['method' => 'POST', 'pattern' => 'api/billing/invoices/*/pay'],
            ['method' => 'GET', 'pattern' => 'api/billing/invoices/*/pix'],
            ['method' => 'GET', 'pattern' => 'api/billing/subscription'],
            ['method' => 'GET', 'pattern' => 'api/billing/plans'],
            ['method' => 'POST', 'pattern' => 'api/billing/plan-change'],
            ['method' => 'GET', 'pattern' => 'api/billing/plan-change/preview'],
            ['method' => 'POST', 'pattern' => 'api/auth/logout'],
            ['method' => 'GET', 'pattern' => 'api/auth/me'],
        ];

        $configuredPatterns = config('billing.delinquency.lockout.allowed_routes', []);
        if (is_array($configuredPatterns)) {
            foreach ($configuredPatterns as $configured) {
                if (is_string($configured) && $configured !== '') {
                    if ($this->matchesRoutePattern($request, null, $configured)) {
                        return true;
                    }

                    continue;
                }

                if (
                    is_array($configured)
                    && is_string($configured['pattern'] ?? null)
                    && $configured['pattern'] !== ''
                ) {
                    $method = is_string($configured['method'] ?? null) ? $configured['method'] : null;
                    if ($this->matchesRoutePattern($request, $method, (string) $configured['pattern'])) {
                        return true;
                    }
                }
            }
        }

        foreach ($allowedRoutes as $allowedRoute) {
            if ($this->matchesRoutePattern($request, $allowedRoute['method'], $allowedRoute['pattern'])) {
                return true;
            }
        }

        return false;
    }

    /** Verifica se a rota corresponde ao método e padrão informados (suporta wildcards). */
    private function matchesRoutePattern(Request $request, ?string $method, string $pattern): bool
    {
        if ($method !== null && strtoupper($request->method()) !== strtoupper($method)) {
            return false;
        }

        return $request->is($pattern);
    }

    /**
     * Retorna a resposta HTTP 423 com detalhes de bloqueio e faturas em aberto.
     *
     * @param  array{billing_status:string,billing_locked_at:string|null,billing_purge_deadline:string|null}  $billingState
     */
    private function lockedResponse(string $tenantId, array $billingState): JsonResponse
    {
        $overdueInvoices = BillingInvoice::query()
            ->withoutGlobalScopes()
            ->where('tenant_id', $tenantId)
            ->where('status', 'overdue')
            ->orderBy('due_date')
            ->get()
            ->map(static fn (BillingInvoice $invoice): array => [
                'id' => (string) $invoice->id,
                'reference_month' => (string) $invoice->reference_month,
                'amount' => (float) $invoice->amount,
                'due_date' => (string) $invoice->due_date,
                'payment_url' => (string) $invoice->payment_url,
            ])
            ->all();

        return response()->json([
            'error' => 'tenant_locked',
            'message' => 'Sua conta está bloqueada por inadimplência. Regularize seu pagamento para restabelecer o acesso.',
            'billing_status' => $billingState['billing_status'],
            'locked_at' => $billingState['billing_locked_at'],
            'overdue_invoices' => $overdueInvoices,
            'purge_deadline' => $billingState['billing_purge_deadline'],
        ], 423);
    }
}
