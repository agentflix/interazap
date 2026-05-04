<?php

declare(strict_types=1);

namespace Database\Seeders;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatInstance;
use Domain\Platform\Actions\PlatformTenantBootstrapAction;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ── Global system data ────────────────────────────────────────────
        $this->call(AuthPermissionSeeder::class);
        $this->call(AiModelPricingSeeder::class);
        $this->call(AdminReportsPermissionSeeder::class);

        // ── Platform plans ────────────────────────────────────────────────
        $this->call(PlatformPlanSeeder::class);
        // PlatformPlanExtraSeeder is intentionally empty — all tiers are in PlatformPlanSeeder
        $this->call(PlatformPlanExtraSeeder::class);
        $defaultPlan = \Domain\Platform\Models\PlatformPlan::query()->where('slug', 'business')->first();

        // ── Default tenant + admin user ───────────────────────────────────
        $tenantId = Config::get('app.default_tenant_id') ?? (string) Str::orderedUuid();

        $tenant = PlatformTenant::query()->withTrashed()->firstOrNew([
            'tenant_code' => 'AGENTFLX',
        ]);

        $tenant->fill([
            'id' => $tenant->id ?: $tenantId,
            'name' => 'InteraZap',
            'primary_email' => 'admin@interazap.com.br',
            'is_active' => true,
            'plan_id' => $defaultPlan?->id,
            'billing_webhook_token' => $tenant->billing_webhook_token ?: (string) Str::uuid(),
        ]);

        $tenant->save();

        if ($tenant->trashed()) {
            $tenant->restore();
        }

        $tenantId = $tenant->id;

        $superAdminRole = AuthRole::query()->firstOrCreate(
            ['name' => AuthRole::SUPER_ADMIN, 'guard_name' => 'sanctum'],
            ['id' => AuthRole::SUPER_ADMIN_ID]
        );

        $roles = [];
        foreach (['inquilino', 'gerente', 'atendente'] as $roleName) {
            $roles[$roleName] = AuthRole::query()->firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'sanctum'],
                ['id' => (string) Str::uuid()]
            );
        }

        $allPermissions = AuthPermission::query()->where('guard_name', 'sanctum')->get();
        $superAdminRole->syncPermissions($allPermissions);

        // ── Role default permissions ─────────────────────────────────────
        $this->call(RolePermissionSeeder::class);

        $admin = AuthUser::query()->withTrashed()->firstOrNew([
            'email' => 'admin@interazap.com.br',
        ]);

        $admin->fill([
            'tenant_id' => $tenant->id,
            'name' => 'Admin InteraZap',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $admin->save();

        if ($admin->trashed()) {
            $admin->restore();
        }

        $admin->assignRole($superAdminRole);

        $rosa = AuthUser::query()->withTrashed()->firstOrNew([
            'email' => 'rosa@interazap.com.br',
        ]);

        $rosa->fill([
            'tenant_id' => $tenant->id,
            'name' => 'Rosa Lopes Pontes',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $rosa->save();

        if ($rosa->trashed()) {
            $rosa->restore();
        }

        $rosa->assignRole($superAdminRole);

        $reportPermissions = AuthPermission::query()
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

        if ($reportPermissions->isNotEmpty()) {
            $admin->givePermissionTo($reportPermissions);
        }

        // ── Default chat instance ─────────────────────────────────────────
        ChatInstance::factory()->create([
            'tenant_id' => $tenantId,
            'provider' => 'uazapi',
            'name' => 'Default Uazapi',
            'status' => 'disconnected',
        ]);

        // ── Global AI system data ─────────────────────────────────────────
        $this->call(AiPromptMasterSeeder::class);
        $this->call(AiPromptSegmentSeeder::class);
        $this->call(AiCatalogSeeder::class);
        $this->call(AiPromptPlanSeeder::class);

        app(PlatformTenantBootstrapAction::class)->execute($tenant);

        // ── Tenant-scoped autopilot tools (required by product-expert agents) ─
        $this->call(AiAutopilotToolSeeder::class);

        // ── Product-expert agents for the default InteraZap tenant ────────
        $this->call(InteraZapProductAgentsSeeder::class);

        // ── Demo / sample data (opt-in via SEED_DEMO_DATA=true) ───────────
        if ((bool) Config::get('app.seed_demo_data', false)) {
            $this->call(DemoSeeder::class);
        }
    }
}
