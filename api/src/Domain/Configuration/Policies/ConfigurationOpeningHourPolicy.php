<?php

declare(strict_types=1);

namespace Domain\Configuration\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Configuration\Models\ConfigurationOpeningHour;

/**
 * Política de autorização para horários de funcionamento.
 *
 * Restringe o acesso e a modificação de horários ao tenant do usuário autenticado.
 */
final class ConfigurationOpeningHourPolicy
{
    /** Permite listar horários se o usuário pertence a um tenant. */
    public function viewAny(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    /** Permite visualizar horários do mesmo tenant. */
    public function view(AuthUser $user, ConfigurationOpeningHour $openingHour): bool
    {
        return $openingHour->tenant_id === $user->tenant_id;
    }

    /** Permite criar horários se o usuário pertence a um tenant. */
    public function create(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    /** Permite atualizar horários do mesmo tenant. */
    public function update(AuthUser $user, ConfigurationOpeningHour $openingHour): bool
    {
        return $openingHour->tenant_id === $user->tenant_id;
    }

    /** Permite excluir horários do mesmo tenant. */
    public function delete(AuthUser $user, ConfigurationOpeningHour $openingHour): bool
    {
        return $openingHour->tenant_id === $user->tenant_id;
    }
}
