<?php

declare(strict_types=1);

namespace Domain\Shared\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Global scope that automatically filters queries by tenant_id.
 *
 * This ensures tenant isolation at the query level, preventing
 * accidental cross-tenant data access (IDOR prevention).
 *
 * Usage in Model:
 * ```php
 * protected static function booted(): void
 * {
 *     static::addGlobalScope(new TenantScope());
 * }
 * ```
 *
 * To bypass (only for admin/system operations):
 * ```php
 * Model::withoutGlobalScope(TenantScope::class)->get();
 * ```
 */
final class TenantScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (\Domain\Shared\Support\TenantContext::isSuperAdminContext()) {
            return;
        }

        $tenantId = \Domain\Shared\Support\TenantContext::get();
        $authGuard = auth();

        if ($tenantId === null) {
            $tenantId = $authGuard->user()?->tenant_id ?? null;
        }

        if ($tenantId === null) {
            return;
        }

        $table = (string) $builder->getQuery()->from;
        $builder->where("{$table}.tenant_id", $tenantId);
    }
}
