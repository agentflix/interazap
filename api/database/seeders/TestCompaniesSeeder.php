<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Seed para criar 3 empresas de teste com 5 usuários cada.
 *
 * Execute: php artisan db:seed --class=TestCompaniesSeeder
 */
class TestCompaniesSeeder extends Seeder
{
    public function run(): void
    {
        $managerRole = AuthRole::firstOrCreate(
            ['name' => AuthRole::MANAGER, 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $adminRole = AuthRole::firstOrCreate(
            ['name' => 'admin', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $companies = [
            ['name' => 'Tech Solutions Ltda', 'email' => 'admin@techsolutions.com.br'],
            ['name' => 'Digital Marketing SA', 'email' => 'admin@digitalmark.com.br'],
            ['name' => 'Consultoria Empresarial Ltda', 'email' => 'admin@consultoria.com.br'],
        ];

        foreach ($companies as $index => $companyData) {
            $tenant = PlatformTenant::factory()->create([
                'name' => $companyData['name'],
                'primary_email' => $companyData['email'],
                'is_active' => true,
            ]);

            $this->command->info("Criou empresa: {$companyData['name']} (ID: {$tenant->id})");

            // Criar 5 usuários para cada empresa
            for ($i = 1; $i <= 5; $i++) {
                $userName = $this->getUserName($index, $i);
                $userEmail = $this->getUserEmail($index, $i);

                $user = AuthUser::factory()->create([
                    'tenant_id' => $tenant->id,
                    'name' => $userName,
                    'email' => $userEmail,
                    'password' => Hash::make('password123'),
                    'is_active' => true,
                ]);

                // Primeiro usuário é manager, os demais são admin
                if ($i === 1) {
                    $user->assignRole($managerRole);
                } else {
                    $user->assignRole($adminRole);
                }

                $this->command->info("  - Usuário: {$userName} ({$userEmail}) - " . ($i === 1 ? 'Gerente' : 'Admin'));
            }
        }

        $this->command->info('');
        $this->command->info('=== Empresas de teste criadas ===');
        $this->command->info('Todas com senha: password123');
        $this->command->info('');
        $this->command->info('Empresa 1: Tech Solutions - admin@techsolutions.com.br');
        $this->command->info('Empresa 2: Digital Marketing - admin@digitalmark.com.br');
        $this->command->info('Empresa 3: Consultoria - admin@consultoria.com.br');
    }

    private function getUserName(int $companyIndex, int $userIndex): string
    {
        $names = [
            ['João Silva', 'Maria Santos', 'Pedro Oliveira', 'Ana Costa', 'Lucas Souza'],
            ['Carlos Mendes', 'Fernanda Lima', 'Roberto Alves', 'Juliana Pereira', 'Marcos Ferreira'],
            ['Ricardo Andrade', 'Patrícia Rocha', 'Fernando Gomes', 'Luciana Martins', 'Daniel Rodrigues'],
        ];

        return $names[$companyIndex][$userIndex - 1];
    }

    private function getUserEmail(int $companyIndex, int $userIndex): string
    {
        $domains = ['techsolutions.com.br', 'digitalmark.com.br', 'consultoria.com.br'];
        return strtolower(str_replace(' ', '.', $this->getUserName($companyIndex, $userIndex))) . '@' . $domains[$companyIndex];
    }
}