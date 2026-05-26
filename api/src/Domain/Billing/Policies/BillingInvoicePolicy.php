<?php

declare(strict_types=1);

namespace Domain\Billing\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Billing\Models\BillingInvoice;
use Laravel\Sanctum\TransientToken;

/**
 * Policy de acesso para Faturas de Billing.
 *
 * Verifica permissões granulares usando Spatie Permission e abilities de token Sanctum.
 */
final class BillingInvoicePolicy
{
    /** Verifica se o usuário pode listar faturas do tenant. */
    public function viewAny(AuthUser $user): bool
    {
        return $this->hasPermission($user, 'billing.invoices.view');
    }

    /** Verifica se o usuário pode visualizar uma fatura específica do seu tenant. */
    public function view(AuthUser $user, BillingInvoice $invoice): bool
    {
        return $invoice->tenant_id === $user->tenant_id
            && $this->hasPermission($user, 'billing.invoices.view');
    }

    /** Verifica se o usuário pode criar faturas. */
    public function create(AuthUser $user): bool
    {
        return $this->hasPermission($user, 'billing.invoices.create');
    }

    /** Verifica se o usuário pode editar uma fatura do seu tenant. */
    public function update(AuthUser $user, BillingInvoice $invoice): bool
    {
        return $invoice->tenant_id === $user->tenant_id
            && $this->hasPermission($user, 'billing.invoices.update');
    }

    /** Verifica se o usuário pode cancelar uma fatura do seu tenant. */
    public function delete(AuthUser $user, BillingInvoice $invoice): bool
    {
        return $invoice->tenant_id === $user->tenant_id
            && $this->hasPermission($user, 'billing.invoices.delete');
    }

    /** Verifica permissão via token Sanctum ou Spatie Permission (precedência ao token). */
    private function hasPermission(AuthUser $user, string $permission): bool
    {
        if ($this->tokenHasAbility($user, $permission)) {
            return true;
        }

        return $user->can($permission);
    }

    /** Verifica se o token Sanctum atual possui a ability informada ou wildcard '*'. */
    private function tokenHasAbility(AuthUser $user, string $permission): bool
    {
        if (! method_exists($user, 'currentAccessToken')) {
            return false;
        }

        $token = $user->currentAccessToken();

        if ($token === null || $token instanceof TransientToken) {
            return false;
        }

        return $user->tokenCan($permission) || $user->tokenCan('*');
    }
}
