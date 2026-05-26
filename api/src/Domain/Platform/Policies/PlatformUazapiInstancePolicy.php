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

    /** Determina se o usuário pode listar instâncias do tenant. */
    public function viewAny(AuthUser $user): bool
    {
        return $this->hasManagePermission($user);
    }

    /** Determina se o usuário pode visualizar uma instância específica. */
    public function view(AuthUser $user, PlatformUazapiInstance $instance): bool
    {
        return $this->belongsToTenant($user, $instance) && $this->hasManagePermission($user);
    }

    /** Determina se o usuário pode criar novas instâncias. */
    public function create(AuthUser $user): bool
    {
        return $this->hasManagePermission($user);
    }

    /** Determina se o usuário pode atualizar uma instância. */
    public function update(AuthUser $user, PlatformUazapiInstance $instance): bool
    {
        return $this->belongsToTenant($user, $instance) && $this->hasManagePermission($user);
    }

    /** Determina se o usuário pode excluir uma instância. */
    public function delete(AuthUser $user, PlatformUazapiInstance $instance): bool
    {
        return $this->belongsToTenant($user, $instance) && $this->hasManagePermission($user);
    }

    /** Verifica se a instância pertence ao tenant do usuário. */
    private function belongsToTenant(AuthUser $user, PlatformUazapiInstance $instance): bool
    {
        return $instance->tenant_id === $user->tenant_id;
    }

    /** Verifica se o usuário possui a permissão de gerenciamento de instâncias. */
    private function hasManagePermission(AuthUser $user): bool
    {
        try {
            return $user->hasPermissionTo(self::PERMISSION_MANAGE, self::GUARD);
        } catch (PermissionDoesNotExist) {
            return false;
        }
    }
}
