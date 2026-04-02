<?php

declare(strict_types=1);

namespace Domain\Billing\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Billing\Models\BillingInvoice;
use Laravel\Sanctum\TransientToken;

/**
 * Policy for Billing Invoices.
 */
final class BillingInvoicePolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $this->hasPermission($user, 'billing.invoices.view');
    }

    public function view(AuthUser $user, BillingInvoice $invoice): bool
    {
        return $invoice->tenant_id === $user->tenant_id
            && $this->hasPermission($user, 'billing.invoices.view');
    }

    public function create(AuthUser $user): bool
    {
        return $this->hasPermission($user, 'billing.invoices.create');
    }

    public function update(AuthUser $user, BillingInvoice $invoice): bool
    {
        return $invoice->tenant_id === $user->tenant_id
            && $this->hasPermission($user, 'billing.invoices.update');
    }

    public function delete(AuthUser $user, BillingInvoice $invoice): bool
    {
        return $invoice->tenant_id === $user->tenant_id
            && $this->hasPermission($user, 'billing.invoices.delete');
    }

    private function hasPermission(AuthUser $user, string $permission): bool
    {
        if ($this->tokenHasAbility($user, $permission)) {
            return true;
        }

        return $user->can($permission);
    }

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
