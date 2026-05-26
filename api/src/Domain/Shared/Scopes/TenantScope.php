<?php

declare(strict_types=1);

namespace Domain\Shared\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Escopo global que filtra automaticamente as queries por tenant_id.
 *
 * Garante isolamento multi-tenant no nível de query, prevenindo
 * acesso cruzado de dados entre tenants (prevenção de IDOR).
 *
 * Uso em model:
 * ```php
 * protected static function booted(): void
 * {
 *     static::addGlobalScope(new TenantScope());
 * }
 * ```
 *
 * Para contornar (somente em operações administrativas/sistema):
 * ```php
 * Model::withoutGlobalScope(TenantScope::class)->get();
 * ```
 */
final class TenantScope implements Scope
{
    /**
     * Aplica o filtro de tenant_id ao query builder Eloquent.
     *
     * Não filtra quando estiver em contexto de super admin ou quando
     * nenhum tenant_id estiver disponível no contexto ou na autenticação.
     *
     * @param  Builder<Model>  $builder  Query builder Eloquent.
     * @param  Model  $model  Instância do modelo sendo consultado.
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
