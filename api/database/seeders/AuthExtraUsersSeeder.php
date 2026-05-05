<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AuthExtraUsersSeeder extends Seeder
{
    /**
     * @var array<string, string>
     */
    private const ROLE_MAP = [
        AuthRole::ADMINISTRADOR_ID => AuthRole::ADMINISTRADOR_NAME,
        AuthRole::GERENTE_ID => AuthRole::GERENTE_NAME,
        AuthRole::ATENDENTE_ID => AuthRole::ATENDENTE_NAME,
    ];

    public function run(): void
    {
        $roles = collect();
        foreach (self::ROLE_MAP as $roleId => $roleName) {
            $roles->push(AuthRole::query()->firstOrCreate(
                ['id' => $roleId],
                ['name' => $roleName, 'guard_name' => 'sanctum']
            ));
        }

        $tenants = PlatformTenant::query()->limit(100)->get();

        foreach ($tenants as $tenant) {
            foreach ($roles as $role) {
                for ($i = 1; $i <= 2; $i++) {
                    $email = sprintf(
                        '%s.%d.%s@interazap.test',
                        $role->name,
                        $i,
                        strtolower((string) $tenant->tenant_code)
                    );

                    $user = AuthUser::query()->firstOrCreate(
                        ['email' => $email],
                        [
                            'id' => (string) Str::orderedUuid(),
                            'tenant_id' => $tenant->id,
                            'name' => ucfirst((string) $role->name).' User '.$i,
                            'password' => bcrypt('password'),
                            'is_active' => $i === 1,
                        ]
                    );

                    if (! $user->roles()->where('id', $role->id)->exists()) {
                        $user->assignRole($role);
                    }
                }
            }
        }

        $this->command->info('Extra users created for each role.');
    }
}
