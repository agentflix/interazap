<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Billing\Enums\BillingInvoiceStatus;
use Domain\Billing\Models\BillingInvoice;
use Domain\Configuration\Models\ConfigurationOpeningHour;
use Domain\CRM\Models\CRMDepartment;
use Domain\CRM\Models\CRMTag;
use Domain\Platform\Enums\PlatformNegotiationsMode;
use Domain\Platform\Enums\PlatformStorageMode;
use Domain\Platform\Models\PlatformPlan;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Support\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $roleNames = [
            AuthRole::SUPER_ADMIN,
            AuthRole::MANAGER,
            AuthRole::AGENT,
        ];

        $roles = $this->ensureRoles($roleNames);
        $this->syncAdminPermissions($roles);

        $this->ensurePlans();
        $plans = PlatformPlan::query()->where('is_active', true)->get();

        if ($plans->isEmpty()) {
            $this->command->error('No plans found. Seeding plans first.');
            $this->call(PlatformPlanSeeder::class);
            $plans = PlatformPlan::query()->where('is_active', true)->get();
        }

        $tenants = $this->ensureTenants(20);

        foreach ($tenants as $tenant) {
            $this->seedDepartments($tenant->id);
            $this->seedTags($tenant->id);
            $this->seedOpeningHours($tenant->id);
            $this->seedUsers($tenant->id, $tenant->tenant_code, $roles);
            $this->seedInvoices($tenant->id, $plans);
        }
    }

    /**
     * @param  list<string>  $roleNames
     * @return array<string, AuthRole>
     */
    private function ensureRoles(array $roleNames): array
    {
        $roles = [];

        foreach ($roleNames as $roleName) {
            $roles[$roleName] = AuthRole::query()->firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'sanctum'],
                ['id' => (string) Str::uuid()]
            );
        }

        return $roles;
    }

    /**
     * @param  array<string, AuthRole>  $roles
     */
    private function syncAdminPermissions(array $roles): void
    {
        $permissions = AuthPermission::query()->where('guard_name', 'sanctum')->get();

        foreach ([AuthRole::SUPER_ADMIN] as $roleName) {
            if (isset($roles[$roleName])) {
                $roles[$roleName]->syncPermissions($permissions);
            }
        }
    }

    private function ensurePlans(): void
    {
        $definitions = [
            'starter' => [
                'name' => 'Starter',
                'limit_users' => 5,
                'storage_mode' => PlatformStorageMode::LIMITED->value,
                'storage_limit_bytes' => 5 * 1024 * 1024 * 1024,
                'ai_enabled' => false,
                'whatsapp_integrations_limit' => 1,
                'negotiations_mode' => PlatformNegotiationsMode::LIMITED->value,
                'negotiations_limit' => 100,
                'price_monthly' => 99.00,
                'is_active' => true,
            ],
            'growth' => [
                'name' => 'Growth',
                'limit_users' => 20,
                'storage_mode' => PlatformStorageMode::LIMITED->value,
                'storage_limit_bytes' => 20 * 1024 * 1024 * 1024,
                'ai_enabled' => true,
                'whatsapp_integrations_limit' => 3,
                'negotiations_mode' => PlatformNegotiationsMode::LIMITED->value,
                'negotiations_limit' => 500,
                'price_monthly' => 199.00,
                'is_active' => true,
            ],
        ];

        foreach ($definitions as $slug => $data) {
            PlatformPlan::query()->firstOrCreate(
                ['slug' => $slug],
                array_merge(
                    ['id' => (string) Str::orderedUuid(), 'slug' => $slug],
                    $data
                )
            );
        }
    }

    /**
     * @return Collection<int, PlatformTenant>
     */
    private function ensureTenants(int $count): Collection
    {
        $tenants = collect();
        $plans = PlatformPlan::query()->where('is_active', true)->get();
        $faker = fake('pt_BR');

        // Default system tenant is excluded — its plan is managed by PlatformPlanSeeder

        for ($index = 1; $index <= $count; $index++) {
            $companyName = $faker->company();
            $tenantCode = strtoupper(Str::slug($companyName, ''));
            $tenantCode = substr($tenantCode, 0, 10).$index;

            $tenant = PlatformTenant::query()->firstOrCreate(
                ['tenant_code' => $tenantCode],
                [
                    'id' => (string) Str::orderedUuid(),
                    'name' => $companyName,
                    'primary_email' => strtolower(Str::slug($companyName)).'@agentflix.local',
                    'billing_webhook_token' => (string) Str::uuid(),
                    'plan_id' => $plans->isNotEmpty() ? $plans->random()->id : null,
                    'is_active' => true,
                ]
            );

            $tenants->push($tenant);
        }

        return $tenants;
    }

    private function seedDepartments(string $tenantId): void
    {
        $departments = [
            ['name' => 'Comercial', 'description' => 'Vendas e prospeccao'],
            ['name' => 'Suporte', 'description' => 'Atendimento e pos-venda'],
            ['name' => 'Financeiro', 'description' => 'Cobranca e faturamento'],
        ];

        $desiredCount = 30;

        foreach ($departments as $department) {
            CRMDepartment::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $department['name']],
                [
                    'id' => (string) Str::orderedUuid(),
                    'description' => $department['description'],
                    'is_active' => true,
                ]
            );
        }

        $existingCount = CRMDepartment::query()
            ->where('tenant_id', $tenantId)
            ->count();

        $missing = $desiredCount - $existingCount;
        if ($missing > 0) {
            CRMDepartment::factory()
                ->count($missing)
                ->create(['tenant_id' => $tenantId]);
        }
    }

    private function seedTags(string $tenantId): void
    {
        $tags = [
            ['name' => 'VIP', 'color' => '#16a34a'],
            ['name' => 'Inadimplente', 'color' => '#dc2626'],
            ['name' => 'Onboarding', 'color' => '#2563eb'],
            ['name' => 'Churn Risk', 'color' => '#f97316'],
            ['name' => 'Upsell', 'color' => '#7c3aed'],
        ];

        foreach ($tags as $tag) {
            CRMTag::query()->firstOrCreate(
                ['tenant_id' => $tenantId, 'name' => $tag['name']],
                [
                    'id' => (string) Str::orderedUuid(),
                    'color' => $tag['color'],
                ]
            );
        }
    }

    private function seedOpeningHours(string $tenantId): void
    {
        $hours = [
            ['day' => 0, 'open' => '09:00', 'close' => '18:00', 'active' => false],
            ['day' => 1, 'open' => '09:00', 'close' => '18:00', 'active' => true],
            ['day' => 2, 'open' => '09:00', 'close' => '18:00', 'active' => true],
            ['day' => 3, 'open' => '09:00', 'close' => '18:00', 'active' => true],
            ['day' => 4, 'open' => '09:00', 'close' => '18:00', 'active' => true],
            ['day' => 5, 'open' => '09:00', 'close' => '18:00', 'active' => true],
            ['day' => 6, 'open' => '09:00', 'close' => '18:00', 'active' => false],
        ];

        foreach ($hours as $hour) {
            ConfigurationOpeningHour::query()->updateOrCreate(
                ['tenant_id' => $tenantId, 'day_of_week' => $hour['day']],
                [
                    'open_time' => $hour['open'],
                    'close_time' => $hour['close'],
                    'is_active' => $hour['active'],
                ]
            );
        }
    }

    /**
     * @param  array<string, AuthRole>  $roles
     */
    private function seedUsers(string $tenantId, string $tenantCode, array $roles): void
    {
        TenantContext::run($tenantId, function () use ($tenantId, $tenantCode, $roles): void {
            foreach ($roles as $roleName => $role) {
                $emailPrefix = str_replace('-', '', $roleName);
                $email = strtolower("{$emailPrefix}.{$tenantCode}@agentflix.local");

                $user = AuthUser::query()->firstOrCreate(
                    ['tenant_id' => $tenantId, 'email' => $email],
                    [
                        'id' => (string) Str::orderedUuid(),
                        'name' => strtoupper($roleName).' '.$tenantCode,
                        'password' => Hash::make('password'),
                        'is_active' => true,
                    ]
                );

                $user->assignRole($role);
            }
        });
    }

    /**
     * @param  Collection<int, PlatformPlan>  $plans
     */
    private function seedInvoices(string $tenantId, Collection $plans): void
    {
        $plan = $plans->random();
        $currentMonth = now()->format('Y-m');
        $lastMonth = now()->subMonth()->format('Y-m');

        BillingInvoice::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'reference_month' => $currentMonth,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'plan_id' => $plan->id,
                'status' => BillingInvoiceStatus::PAID,
                'amount' => $plan->price_monthly,
                'due_date' => now()->addMonth(),
                'paid_at' => now(),
                'payment_method' => 'pix',
            ]
        );

        BillingInvoice::query()->firstOrCreate(
            [
                'tenant_id' => $tenantId,
                'reference_month' => $lastMonth,
            ],
            [
                'id' => (string) Str::orderedUuid(),
                'plan_id' => $plan->id,
                'status' => BillingInvoiceStatus::PENDING,
                'amount' => $plan->price_monthly,
                'due_date' => now()->subDays(5),
                'paid_at' => null,
                'payment_method' => 'pix',
            ]
        );
    }
}
