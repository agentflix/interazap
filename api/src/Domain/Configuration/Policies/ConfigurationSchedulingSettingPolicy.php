<?php

declare(strict_types=1);

namespace Domain\Configuration\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Configuration\Models\ConfigurationSchedulingSetting;

/**
 * Política de autorização para configurações de agendamento.
 *
 * Restringe o acesso e a modificação das configurações ao tenant do usuário autenticado.
 */
final class ConfigurationSchedulingSettingPolicy
{
    /** Permite listar configurações se o usuário pertence a um tenant. */
    public function viewAny(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    /** Permite visualizar configurações do mesmo tenant. */
    public function view(AuthUser $user, ConfigurationSchedulingSetting $setting): bool
    {
        return $setting->tenant_id === $user->tenant_id;
    }

    /** Permite criar configurações se o usuário pertence a um tenant. */
    public function create(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    /** Permite atualizar configurações do mesmo tenant. */
    public function update(AuthUser $user, ConfigurationSchedulingSetting $setting): bool
    {
        return $setting->tenant_id === $user->tenant_id;
    }

    /** Permite excluir configurações do mesmo tenant. */
    public function delete(AuthUser $user, ConfigurationSchedulingSetting $setting): bool
    {
        return $setting->tenant_id === $user->tenant_id;
    }
}
