<?php

declare(strict_types=1);

namespace Domain\Billing\Actions;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatInstance;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Domain\Platform\Services\PlatformPlanEnforcementService;
use Illuminate\Support\Carbon;

/**
 * Gera preview de impacto financeiro e operacional para troca de plano.
 */
final class BillingPlanChangePreviewAction
{
    public function __construct(
        private readonly PlatformPlanEnforcementService $enforcementService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function execute(string $tenantId, string $newPlanId): array
    {
        $tenant = PlatformTenant::query()->withoutGlobalScopes()->findOrFail($tenantId);

        $newPlan = PlatformPlan::query()
            ->where('id', $newPlanId)
            ->where('is_active', true)
            ->firstOrFail();

        $currentPlan = $this->enforcementService->getCurrentPlan($tenantId);

        if ($currentPlan !== null && $currentPlan->id === $newPlan->id) {
            throw new \DomainException('plan_unchanged');
        }

        $type = $this->resolveType($currentPlan, $newPlan);
        $daysInMonth = (int) now()->daysInMonth;
        $daysRemaining = $this->daysRemainingInclusive();

        $currentPrice = $currentPlan === null ? 0.0 : (float) $currentPlan->price_monthly;
        $newPrice = (float) $newPlan->price_monthly;

        $financial = [
            'pro_rata_charge' => null,
            'pro_rata_credit' => null,
            'days_remaining' => $daysRemaining,
            'days_in_month' => $daysInMonth,
            'formula' => null,
        ];

        if ($type === 'upgrade') {
            $charge = ($newPrice - $currentPrice) * ($daysRemaining / $daysInMonth);
            $charge = max(0.0, ceil($charge * 100) / 100);

            $financial['pro_rata_charge'] = number_format($charge, 2, '.', '');
            $financial['formula'] = sprintf(
                '(%0.2f - %0.2f) × %d / %d = %0.2f',
                $newPrice,
                $currentPrice,
                $daysRemaining,
                $daysInMonth,
                $charge,
            );
        } elseif ($type === 'downgrade') {
            $credit = ($currentPrice - $newPrice) * ($daysRemaining / $daysInMonth);
            $credit = max(0.0, floor($credit * 100) / 100);

            $financial['pro_rata_credit'] = number_format($credit, 2, '.', '');
            $financial['formula'] = sprintf(
                '(%0.2f - %0.2f) × %d / %d = %0.2f',
                $currentPrice,
                $newPrice,
                $daysRemaining,
                $daysInMonth,
                $credit,
            );
        }

        return [
            'type' => $type,
            'new_plan' => [
                'id' => $newPlan->id,
                'name' => $newPlan->name,
                'price_monthly' => number_format((float) $newPlan->price_monthly, 2, '.', ''),
            ],
            'financial' => $financial,
            'affected_resources' => $this->calculateAffectedResources($tenant, $newPlan),
        ];
    }

    private function resolveType(?PlatformPlan $currentPlan, PlatformPlan $newPlan): string
    {
        if ($currentPlan === null) {
            return 'upgrade';
        }

        return ((float) $newPlan->price_monthly >= (float) $currentPlan->price_monthly) ? 'upgrade' : 'downgrade';
    }

    /**
     * @return array<string, mixed>
     */
    private function calculateAffectedResources(PlatformTenant $tenant, PlatformPlan $newPlan): array
    {
        $tenantId = (string) $tenant->id;

        $activeUsersCount = AuthUser::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        $protectedIds = AuthUser::query()
            ->where('tenant_id', $tenantId)
            ->where(function ($query) use ($tenant): void {
                $query->where('email', $tenant->primary_email)
                    ->orWhereHas('roles', static function ($roles): void {
                        $roles->where('id', AuthRole::ADMINISTRADOR_ID);
                    });
            })
            ->pluck('id')
            ->all();

        $deactivatableUsers = AuthUser::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($protectedIds !== [], static function ($query) use ($protectedIds): void {
                $query->whereNotIn('id', $protectedIds);
            })
            ->orderBy('updated_at')
            ->orderBy('created_at')
            ->get(['id', 'name', 'updated_at']);

        $usersOverLimit = max(0, $activeUsersCount - (int) $newPlan->limit_users);
        $usersToDeactivate = min($usersOverLimit, $deactivatableUsers->count());

        $activeInstances = ChatInstance::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('created_at')
            ->get(['id', 'name', 'created_at']);

        $instanceLimit = (int) $newPlan->chat_channels_limit;
        $instancesOverLimit = max(0, $activeInstances->count() - $instanceLimit);

        $storageUsed = $this->enforcementService->getStorageUsageBytes($tenantId);
        $newStorageLimit = $newPlan->storage_limit_bytes;
        $isStorageLimited = $newPlan->isStorageLimited();

        $storageOverLimit = $isStorageLimited
            && is_numeric($newStorageLimit)
            && $newStorageLimit > 0
            && $storageUsed > (int) $newStorageLimit;

        return [
            'users' => [
                'will_deactivate' => $usersToDeactivate,
                'current' => $activeUsersCount,
                'new_limit' => (int) $newPlan->limit_users,
                'affected' => $deactivatableUsers
                    ->take($usersToDeactivate)
                    ->map(static fn (AuthUser $user): array => [
                        'id' => (string) $user->id,
                        'name' => (string) $user->name,
                        'last_login_at' => Carbon::parse((string) $user->updated_at)->toDateString(),
                    ])
                    ->values()
                    ->all(),
            ],
            'instances' => [
                'will_deactivate' => $instancesOverLimit,
                'current' => $activeInstances->count(),
                'new_limit' => $instanceLimit,
                'affected' => $activeInstances
                    ->take($instancesOverLimit)
                    ->map(static fn (ChatInstance $instance): array => [
                        'id' => (string) $instance->id,
                        'name' => (string) $instance->name,
                        'created_at' => $instance->created_at->toDateString(),
                    ])
                    ->values()
                    ->all(),
            ],
            'storage' => [
                'is_over_limit' => $storageOverLimit,
                'current_bytes' => $storageUsed,
                'new_limit_bytes' => $isStorageLimited ? $newStorageLimit : null,
                'impact' => $storageOverLimit
                    ? 'Upload de novos arquivos e download de anexos em mensagens serão bloqueados'
                    : 'none',
            ],
            'negotiations' => [
                'impact' => 'none',
                'message' => 'Negociações não serão afetadas',
            ],
        ];
    }

    private function daysRemainingInclusive(): int
    {
        $today = now()->startOfDay();
        $monthEnd = $today->copy()->endOfMonth()->startOfDay();

        return (int) $today->diffInDays($monthEnd) + 1;
    }
}
