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
    public function run(): void
    {
        $defaultRoles = [AuthRole::SUPER_ADMIN, 'gerente', 'atendente'];

        $roles = collect();
        foreach ($defaultRoles as $roleName) {
            $roles->push(AuthRole::query()->firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'sanctum'],
                ['id' => (string) Str::uuid()]
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

                    if (! $user->hasRole($role->name)) {
                        $user->assignRole($role);
                    }
                }
            }
        }

        $this->command->info('Extra users created for each role.');
    }
}
