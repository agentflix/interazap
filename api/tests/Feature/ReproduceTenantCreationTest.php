<?php

declare(strict_types=1);

use Database\Seeders\AiPromptMasterSeeder;
use Database\Seeders\AiPromptSegmentSeeder;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Actions\PlatformTenantActions;
use Domain\Platform\Actions\PlatformTenantBootstrapAction;
use Domain\Platform\DTOs\PlatformTenantDTO;
use Domain\Platform\Models\PlatformTenant;

test('reproduce tenant creation error via actions', function (): void {
    $this->seed(AiPromptMasterSeeder::class);
    $this->seed(AiPromptSegmentSeeder::class);

    $role = \Domain\Auth\Models\AuthRole::query()->firstOrCreate(['id' => AuthRole::ADMINISTRADOR_ID], ['name' => AuthRole::ADMINISTRADOR_NAME, 'guard_name' => 'sanctum']);

    $tenant = PlatformTenant::factory()->create();

    $user = AuthUser::factory()->create([
        'tenant_id' => $tenant->id,
    ]);
    $user->assignRole(AuthRole::ADMINISTRADOR_ID);

    $actions = app(PlatformTenantActions::class);
    $bootstrap = app(PlatformTenantBootstrapAction::class);

    $dto = new PlatformTenantDTO(
        name: 'Test Tenant',
        primaryEmail: 'test@example.com',
        document: '12345678000195',
        tenantCode: null,
        isActive: true,
        segmentId: null,
        phone: null,
        street: null,
        number: null,
        complement: null,
        district: null,
        city: null,
        state: null,
        zipCode: null,
    );

    $createdTenant = $actions->create($dto, $user);
    $bootstrap->execute($createdTenant);

    expect(true)->toBeTrue();
});
