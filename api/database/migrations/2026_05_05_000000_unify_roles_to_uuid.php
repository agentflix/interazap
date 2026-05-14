<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const array SYSTEM_ROLES = [
        ['id' => AuthRole::ADMINISTRADOR_ID, 'name' => AuthRole::ADMINISTRADOR_NAME],
        ['id' => AuthRole::INQUILINO_ID, 'name' => AuthRole::INQUILINO_NAME],
        ['id' => AuthRole::GERENTE_ID, 'name' => AuthRole::GERENTE_NAME],
        ['id' => AuthRole::ATENDENTE_ID, 'name' => AuthRole::ATENDENTE_NAME],
    ];

    public function up(): void
    {
        foreach (self::SYSTEM_ROLES as $config) {
            DB::table('auth_roles')->insertOrIgnore([
                'id' => $config['id'],
                'name' => $config['name'],
                'guard_name' => 'sanctum',
            ]);
        }

        Artisan::call('permission:cache-reset');
    }

    public function down(): void
    {
        foreach (self::SYSTEM_ROLES as $config) {
            DB::table('auth_roles')->where('id', $config['id'])->delete();
        }

        Artisan::call('permission:cache-reset');
    }
};
