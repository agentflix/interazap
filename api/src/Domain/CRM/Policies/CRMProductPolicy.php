<?php

declare(strict_types=1);

namespace Domain\CRM\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\CRM\Models\CRMProduct;

/**
 * Policy para produtos/serviços do catálogo CRM.
 *
 * Controla o acesso às operações de produtos garantindo isolamento por tenant.
 */
final class CRMProductPolicy
{
    /** Permite listar se o usuário pertence a um tenant. */
    public function viewAny(AuthUser $user): bool
    {
        return (string) $user->tenant_id !== '';
    }

    /** Permite visualizar apenas produtos do mesmo tenant. */
    public function view(AuthUser $user, CRMProduct $product): bool
    {
        return $product->tenant_id === $user->tenant_id;
    }

    /** Permite criar se o usuário pertence a um tenant. */
    public function create(AuthUser $user): bool
    {
        return (string) $user->tenant_id !== '';
    }

    /** Permite atualizar apenas produtos do mesmo tenant. */
    public function update(AuthUser $user, CRMProduct $product): bool
    {
        return $product->tenant_id === $user->tenant_id;
    }

    /** Permite excluir apenas produtos do mesmo tenant. */
    public function delete(AuthUser $user, CRMProduct $product): bool
    {
        return $product->tenant_id === $user->tenant_id;
    }
}
