<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Illuminate\Database\Seeder;

final class AdminReportsPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $admin = AuthUser::query()->where('email', 'admin@agentflix.com.br')->first();

        if ($admin === null) {
            return;
        }

        $permissions = AuthPermission::query()
            ->where('guard_name', 'sanctum')
            ->whereIn('name', [
                'reports.crm.view',
                'reports.chat.view',
                'reports.ai.view',
                'reports.billing.view',
                'reports.admin.view',
                'reports.export',
            ])
            ->get();

        if ($permissions->isNotEmpty()) {
            $admin->givePermissionTo($permissions);
        }
    }
}
