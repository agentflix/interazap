<?php

declare(strict_types=1);

namespace Domain\Auth\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Permission;

/**
 * Permission com tabela prefixada e UUID.
 *
 * Não pode ser `final` pois Spatie\Permission\Models\Permission exige
 * extensibilidade para resolução dinâmica de modelos via `app(Permission::class)`.
 */
class AuthPermission extends Permission
{
    use HasUuids;

    protected $table = 'auth_permissions';

    /** @var string */
    protected $guard_name = 'sanctum';

    public $incrementing = false;

    protected $keyType = 'string';
}
