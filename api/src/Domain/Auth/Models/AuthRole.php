<?php

declare(strict_types=1);

namespace Domain\Auth\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Spatie\Permission\Models\Role;

/**
 * Role com tabela prefixada e UUID.
 *
 * Não pode ser `final` pois Spatie\Permission\Models\Role exige
 * extensibilidade para resolução dinâmica de modelos via `app(Role::class)`.
 */
class AuthRole extends Role
{
    use HasUuids;

    public const SUPER_ADMIN = 'super-admin';

    public const MANAGER = 'Gerente';

    public const AGENT = 'Atendente';

    public const SUPER_ADMIN_ID = '00000000-0000-4000-8000-000000000001';

    protected $table = 'auth_roles';

    /** @var string */
    protected $guard_name = 'sanctum';

    public $incrementing = false;

    protected $keyType = 'string';
}
