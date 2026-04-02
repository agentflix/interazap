<?php

declare(strict_types=1);

namespace Domain\Platform\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformUazapiInstance;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

/**
 * Policy para gestão de instâncias Uazapi por tenant.
 */
final class PlatformUazapiInstancePolicy
{
    private const PERMISSION_MANAGE = 'whatsapp.manage';

    private const GUARD = 'sanctum';

    public function viewAny(AuthUser $user): bool
    {
        return $this->hasManagePermission($user);
    }

    public function view(AuthUser $user, PlatformUazapiInstance $instance): bool
    {
        return $this->belongsToTenant($user, $instance) && $this->hasManagePermission($user);
    }

    public function create(AuthUser $user): bool
    {
        return $this->hasManagePermission($user);
    }

    public function update(AuthUser $user, PlatformUazapiInstance $instance): bool
    {
        return $this->belongsToTenant($user, $instance) && $this->hasManagePermission($user);
    }

    public function delete(AuthUser $user, PlatformUazapiInstance $instance): bool
    {
        return $this->belongsToTenant($user, $instance) && $this->hasManagePermission($user);
    }

    private function belongsToTenant(AuthUser $user, PlatformUazapiInstance $instance): bool
    {
        return $instance->tenant_id === $user->tenant_id;
    }

    private function hasManagePermission(AuthUser $user): bool
    {
        try {
            return $user->hasPermissionTo(self::PERMISSION_MANAGE, self::GUARD);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
