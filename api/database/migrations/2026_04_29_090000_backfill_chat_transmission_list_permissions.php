<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const GUARD = 'sanctum';

    /**
     * @var list<string>
     */
    private const PERMISSIONS = [
        'chat.transmission_lists.view',
        'chat.transmission_lists.create',
        'chat.transmission_lists.update',
        'chat.transmission_lists.delete',
    ];

    /**
     * @var list<string>
     */
    private const ADMIN_ROLE_IDS = [
        AuthRole::ADMINISTRADOR_ID,
        AuthRole::INQUILINO_ID,
        AuthRole::GERENTE_ID,
    ];

    public function up(): void
    {
        foreach (self::PERMISSIONS as $permissionName) {
            AuthPermission::query()->firstOrCreate(
                ['name' => $permissionName, 'guard_name' => self::GUARD],
                ['id' => (string) Str::orderedUuid()]
            );
        }

        $permissions = AuthPermission::query()
            ->where('guard_name', self::GUARD)
            ->whereIn('name', self::PERMISSIONS)
            ->get();

        if ($permissions->isEmpty()) {
            return;
        }

        $roles = AuthRole::query()
            ->where('guard_name', self::GUARD)
            ->whereIn('id', self::ADMIN_ROLE_IDS)
            ->get();

        foreach ($roles as $role) {
            $role->givePermissionTo($permissions);
        }
    }

    public function down(): void
    {
        // Intencionalmente sem rollback destrutivo para evitar remover permissões já em uso.
    }
};
