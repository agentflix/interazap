<?php

declare(strict_types=1);

namespace Domain\Shared\Concerns;

use Domain\Shared\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait to be used by Models that belong to a tenant.
 *
 * Automatically applies TenantScope to filter queries by tenant_id,
 * ensuring tenant isolation and preventing IDOR vulnerabilities.
 *
 * Usage:
 * ```php
 * class MyModel extends Model
 * {
 *     use BelongsToTenant;
 * }
 * ```
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    /**
     * Boot the trait.
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    /**
     * Get the tenant relationship.
     */
    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Domain\Platform\Models\PlatformTenant::class, 'tenant_id');
    }

    /**
     * Scope a query to a specific tenant (bypasses global scope).
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class)
            ->where($this->getTable().'.tenant_id', $tenantId);
    }
}
