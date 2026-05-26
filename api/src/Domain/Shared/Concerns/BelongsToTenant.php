<?php

declare(strict_types=1);

namespace Domain\Shared\Concerns;

use Domain\Shared\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait para modelos que pertencem a um tenant.
 *
 * Aplica automaticamente o TenantScope às queries por tenant_id,
 * garantindo isolamento multi-tenant e prevenindo vulnerabilidades IDOR.
 *
 * Uso:
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
     * Inicializa o trait registrando o TenantScope global no modelo.
     */
    protected static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);
    }

    /**
     * Retorna o relacionamento com o tenant dono do modelo.
     */
    public function tenant(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(\Domain\Platform\Models\PlatformTenant::class, 'tenant_id');
    }

    /**
     * Escopo para filtrar explicitamente por tenant, contornando o escopo global.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  string  $tenantId  Identificador do tenant a filtrar.
     * @return Builder<TModel>
     */
    public function scopeForTenant(Builder $query, string $tenantId): Builder
    {
        return $query->withoutGlobalScope(TenantScope::class)
            ->where($this->getTable().'.tenant_id', $tenantId);
    }
}
