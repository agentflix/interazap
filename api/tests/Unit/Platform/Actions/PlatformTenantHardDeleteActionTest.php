<?php

declare(strict_types=1);

namespace Tests\Unit\Platform\Actions;

use Domain\Auth\Models\AuthUser;
use Domain\Billing\Enums\BillingPaymentStatus;
use Domain\Billing\Models\BillingPayment;
use Domain\Chat\Models\ChatInstance;
use Domain\CRM\Models\CRMContact;
use Domain\Platform\Actions\PlatformTenantHardDeleteAction;
use Domain\Platform\Events\PlatformTenantPurgedEvent;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformTenantHardDeleteActionTest extends TestCase
{
    public function test_purges_tenant_and_data_successfully(): void
    {
        Event::fake([PlatformTenantPurgedEvent::class]);

        $tenant = PlatformTenant::factory()->create();
        ChatInstance::factory()->create(['tenant_id' => $tenant->id]);
        CRMContact::factory()->create(['tenant_id' => $tenant->id]);

        $actor = AuthUser::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $action = app(PlatformTenantHardDeleteAction::class);
        $result = $action->execute($tenant, 'correct-password', $actor);

        $this->assertTrue($result['purged']);
        $this->assertDatabaseMissing('platform_tenants', ['id' => $tenant->id]);
        $this->assertDatabaseMissing('chat_instances', ['tenant_id' => $tenant->id]);
        $this->assertDatabaseMissing('crm_contacts', ['tenant_id' => $tenant->id]);

        Event::assertDispatched(PlatformTenantPurgedEvent::class, function (PlatformTenantPurgedEvent $event) use ($tenant): bool {
            return $event->tenantId === (string) $tenant->id;
        });
    }

    public function test_throws_on_incorrect_password(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $actor = AuthUser::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Senha de administrador incorreta.');

        app(PlatformTenantHardDeleteAction::class)->execute($tenant, 'wrong-password', $actor);
    }

    public function test_throws_on_protected_default_tenant(): void
    {
        $tenant = PlatformTenant::factory()->create();
        config(['app.default_tenant_id' => (string) $tenant->id]);

        $actor = AuthUser::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Empresa principal InteraZap não pode ser excluída.');

        app(PlatformTenantHardDeleteAction::class)->execute($tenant, 'correct-password', $actor);
    }

    public function test_throws_on_recent_payment(): void
    {
        $tenant = PlatformTenant::factory()->create();
        BillingPayment::factory()->create([
            'tenant_id' => $tenant->id,
            'confirmed_at' => now()->subDays(5),
            'status' => BillingPaymentStatus::CONFIRMED,
        ]);

        $actor = AuthUser::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenant possui pagamento nos últimos 30 dias. Purge bloqueado.');

        app(PlatformTenantHardDeleteAction::class)->execute($tenant, 'correct-password', $actor);
    }

    public function test_throws_on_super_admin_user(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $superAdmin = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('super-password'),
        ]);

        $superAdminRole = \Domain\Auth\Models\AuthRole::query()->firstOrCreate(
            ['id' => \Domain\Auth\Models\AuthRole::ADMINISTRADOR_ID],
            ['name' => \Domain\Auth\Models\AuthRole::ADMINISTRADOR_NAME, 'guard_name' => 'sanctum']
        );
        $superAdmin->assignRole(\Domain\Auth\Models\AuthRole::ADMINISTRADOR_ID);

        $actor = AuthUser::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tenant possui usuário super-admin. Purge bloqueado.');

        app(PlatformTenantHardDeleteAction::class)->execute($tenant, 'correct-password', $actor);
    }
}
