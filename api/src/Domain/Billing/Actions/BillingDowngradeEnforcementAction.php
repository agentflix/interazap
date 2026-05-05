<?php

declare(strict_types=1);

namespace Domain\Billing\Actions;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatInstance;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;

/**
 * Aplica enforcement operacional após downgrade de plano.
 */
final class BillingDowngradeEnforcementAction
{
    /**
     * @return array{users_deactivated:int,instances_deactivated:int,storage_blocked:bool,negotiations_affected:bool}
     */
    public function execute(string $tenantId, PlatformPlan $newPlan): array
    {
        $tenant = PlatformTenant::query()->withoutGlobalScopes()->findOrFail($tenantId);

        $usersDeactivated = $this->deactivateUsersOverLimit($tenant, (int) $newPlan->limit_users);
        $instancesDeactivated = $this->deactivateInstancesOverLimit($tenantId, (int) $newPlan->chat_channels_limit);

        return [
            'users_deactivated' => $usersDeactivated,
            'instances_deactivated' => $instancesDeactivated,
            'storage_blocked' => false,
            'negotiations_affected' => false,
        ];
    }

    private function deactivateUsersOverLimit(PlatformTenant $tenant, int $newLimit): int
    {
        $tenantId = (string) $tenant->id;

        $activeUsersCount = AuthUser::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        if ($newLimit <= 0 || $activeUsersCount <= $newLimit) {
            return 0;
        }

        $toDeactivate = $activeUsersCount - $newLimit;

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

        $users = AuthUser::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->when($protectedIds !== [], static function ($query) use ($protectedIds): void {
                $query->whereNotIn('id', $protectedIds);
            })
            ->orderBy('updated_at')
            ->orderBy('created_at')
            ->take($toDeactivate)
            ->get();

        foreach ($users as $user) {
            $user->is_active = false;
            $user->save();
        }

        return $users->count();
    }

    private function deactivateInstancesOverLimit(string $tenantId, int $newLimit): int
    {
        $activeInstancesCount = ChatInstance::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->count();

        if ($newLimit <= 0 || $activeInstancesCount <= $newLimit) {
            return 0;
        }

        $toDeactivate = $activeInstancesCount - $newLimit;

        $instances = ChatInstance::query()
            ->where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('created_at')
            ->take($toDeactivate)
            ->get();

        foreach ($instances as $instance) {
            $instance->is_active = false;
            $instance->save();
        }

        return $instances->count();
    }
}
