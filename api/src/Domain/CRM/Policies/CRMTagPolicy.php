<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMTag;

/**
 * Policy para tags do CRM.
 *
 * Controla o acesso às operações de tags garantindo isolamento por tenant.
 */
final class CRMTagPolicy
{
    /** Permite listar se o usuário pertence a um tenant. */
    public function viewAny(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    /** Permite visualizar apenas tags do mesmo tenant. */
    public function view(AuthUser $user, CRMTag $tag): bool
    {
        return $tag->tenant_id === $user->tenant_id;
    }

    /** Permite criar se o usuário pertence a um tenant. */
    public function create(AuthUser $user): bool
    {
        return (bool) $user->tenant_id;
    }

    /**
     * Permite atualizar tags do mesmo tenant.
     *
     * Quando $tag é null (operação de vínculo/desvínculo), basta o usuário ter tenant.
     */
    public function update(AuthUser $user, ?CRMTag $tag = null): bool
    {
        if (! $user->tenant_id) {
            return false;
        }

        if ($tag === null) {
            return true;
        }

        return $tag->tenant_id === $user->tenant_id;
    }

    /** Permite excluir apenas tags do mesmo tenant. */
    public function delete(AuthUser $user, CRMTag $tag): bool
    {
        return $tag->tenant_id === $user->tenant_id;
    }
}
