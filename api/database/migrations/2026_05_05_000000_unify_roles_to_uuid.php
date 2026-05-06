<?php

declare(strict_types=1);

use Domain\Auth\Models\AuthRole;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

/**
 * Cria as 4 roles de sistema com UUIDs fixos e nomes padronizados.
 *
 * Banco limpo — sem lógica de migração de dados legados.
 */
final class UnifyRolesToUuid extends Migration
{
    /**
     * @var array<string, array{id: string, name: string}>
     */
    private const SYSTEM_ROLES = [
        'Administrador' => [
            'id' => AuthRole::ADMINISTRADOR_ID,
            'name' => AuthRole::ADMINISTRADOR_NAME,
        ],
        'Inquilino' => [
            'id' => AuthRole::INQUILINO_ID,
            'name' => AuthRole::INQUILINO_NAME,
        ],
        'Gerente' => [
            'id' => AuthRole::GERENTE_ID,
            'name' => AuthRole::GERENTE_NAME,
        ],
        'Atendente' => [
            'id' => AuthRole::ATENDENTE_ID,
            'name' => AuthRole::ATENDENTE_NAME,
        ],
    ];

    public function up(): void
    {
        foreach (self::SYSTEM_ROLES as $config) {
            DB::table('auth_roles')->insert([
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
}
