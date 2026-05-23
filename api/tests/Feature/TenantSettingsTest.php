<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class TenantSettingsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        AuthPermission::query()->firstOrCreate(
            ['name' => 'platform.tenants.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_get_settings_returns_defaults_for_new_tenant(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $admin = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $admin->givePermissionTo('platform.tenants.manage');

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/platform/tenants/{$tenant->id}/settings")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'settings_localization' => [
                        'timezone' => 'America/Sao_Paulo',
                        'dateFormat' => 'DD/MM/YYYY',
                        'timeFormat' => '24h',
                        'currencyFormat' => 'BRL',
                    ],
                    'settings_privacy' => [
                        'presence' => 'team',
                        'readReceipt' => true,
                        'notificationPreview' => true,
                    ],
                ],
            ]);
    }

    public function test_get_settings_returns_merged_stored_values(): void
    {
        $tenant = PlatformTenant::factory()->create([
            'settings_localization' => [
                'timezone' => 'UTC',
                'dateFormat' => 'MM/DD/YYYY',
            ],
        ]);

        $admin = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $admin->givePermissionTo('platform.tenants.manage');

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/platform/tenants/{$tenant->id}/settings")
            ->assertOk()
            ->assertJson([
                'data' => [
                    'settings_localization' => [
                        'timezone' => 'UTC',
                        'dateFormat' => 'MM/DD/YYYY',
                        'timeFormat' => '24h',
                        'currencyFormat' => 'BRL',
                    ],
                ],
            ]);
    }

    public function test_super_admin_can_read_settings_from_any_tenant(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $superAdmin = AuthUser::factory()->create();
        $superAdmin->assignRole(\Domain\Auth\Models\AuthRole::ADMINISTRADOR_ID);

        $this->actingAs($superAdmin, 'sanctum')
            ->getJson("/api/platform/tenants/{$tenant->id}/settings")
            ->assertOk();
    }

    public function test_user_without_permission_cannot_read_settings(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $user = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        // No permission assigned

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/platform/tenants/{$tenant->id}/settings")
            ->assertForbidden();
    }

    public function test_patch_settings_does_deep_merge(): void
    {
        $tenant = PlatformTenant::factory()->create([
            'settings_localization' => [
                'timezone' => 'UTC',
                'dateFormat' => 'YYYY-MM-DD',
                'timeFormat' => '12h',
                'currencyFormat' => 'USD',
            ],
            'settings_privacy' => [
                'presence' => 'all',
                'readReceipt' => false,
                'notificationPreview' => false,
            ],
        ]);

        $admin = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $admin->givePermissionTo('platform.tenants.manage');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/platform/tenants/{$tenant->id}/settings", [
                'settings_localization' => ['timezone' => 'America/Sao_Paulo'],
            ])
            ->assertOk()
            ->assertJson([
                'data' => [
                    'settings_localization' => [
                        'timezone' => 'America/Sao_Paulo',
                        'dateFormat' => 'YYYY-MM-DD', // preserved
                        'timeFormat' => '12h',          // preserved
                        'currencyFormat' => 'USD',     // preserved
                    ],
                    'settings_privacy' => [
                        'presence' => 'all',
                        'readReceipt' => false,
                        'notificationPreview' => false,
                    ],
                ],
            ]);
    }

    public function test_patch_settings_with_invalid_timezone_returns_422(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $admin = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $admin->givePermissionTo('platform.tenants.manage');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/platform/tenants/{$tenant->id}/settings", [
                'settings_localization' => ['timezone' => 'Invalid/Timezone'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings_localization.timezone']);
    }

    public function test_patch_settings_with_invalid_date_format_returns_422(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $admin = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $admin->givePermissionTo('platform.tenants.manage');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/platform/tenants/{$tenant->id}/settings", [
                'settings_localization' => ['dateFormat' => 'invalid'],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['settings_localization.dateFormat']);
    }

    public function test_patch_privacy_settings(): void
    {
        $tenant = PlatformTenant::factory()->create();
        $admin = AuthUser::factory()->create([
            'tenant_id' => $tenant->id,
        ]);
        $admin->givePermissionTo('platform.tenants.manage');

        $this->actingAs($admin, 'sanctum')
            ->patchJson("/api/platform/tenants/{$tenant->id}/settings", [
                'settings_privacy' => [
                    'presence' => 'hidden',
                    'readReceipt' => false,
                    'notificationPreview' => true,
                ],
            ])
            ->assertOk()
            ->assertJson([
                'data' => [
                    'settings_privacy' => [
                        'presence' => 'hidden',
                        'readReceipt' => false,
                        'notificationPreview' => true,
                    ],
                ],
            ]);
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $tenant = PlatformTenant::factory()->create();

        $this->getJson("/api/platform/tenants/{$tenant->id}/settings")
            ->assertUnauthorized();
    }
}
