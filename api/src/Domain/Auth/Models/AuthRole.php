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

    // ── Roles de sistema com UUID fixo ──────────────────────────────────

    /** Identificador fixo do super-admin — exclusivo para a conta InteraZap. */
    public const ADMINISTRADOR_ID = '00000000-0000-4000-8000-000000000001';

    public const ADMINISTRADOR_NAME = 'Administrador';

    /** Tenant owner — unifica admin + inquilino */
    public const INQUILINO_ID = '00000000-0000-4000-8000-000000000003';

    public const INQUILINO_NAME = 'Inquilino';

    /** Tenant manager */
    public const GERENTE_ID = '00000000-0000-4000-8000-000000000004';

    public const GERENTE_NAME = 'Gerente';

    /** Tenant agent */
    public const ATENDENTE_ID = '00000000-0000-4000-8000-000000000005';

    public const ATENDENTE_NAME = 'Atendente';

    protected $table = 'auth_roles';

    /** @var string */
    protected $guard_name = 'sanctum';

    public $incrementing = false;

    protected $keyType = 'string';
}
