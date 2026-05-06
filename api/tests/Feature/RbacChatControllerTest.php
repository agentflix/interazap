<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

final class RbacChatControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_chat_ticket_index_returns_403_without_permission(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = AuthUser::factory()->create();
        Sanctum::actingAs($user, abilities: []);

        $this->getJson('/api/chat/tickets')->assertStatus(403);
    }

    public function test_chat_ticket_index_returns_200_with_permission(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'chat.tickets.view', 'guard_name' => 'sanctum'],
            ['id' => (string) Str::orderedUuid()]
        );

        $user = AuthUser::factory()->create();
        $user->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Sanctum::actingAs($user->fresh(), abilities: []);

        $this->getJson('/api/chat/tickets')->assertStatus(200);
    }

    public function test_admin_role_bypasses_chat_ticket_permission_gate(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $adminRole = AuthRole::query()->firstOrCreate(
            ['id' => AuthRole::INQUILINO_ID],
            ['name' => AuthRole::INQUILINO_NAME, 'guard_name' => 'sanctum']
        );

        $user = AuthUser::factory()->create();
        $user->assignRole($adminRole);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Sanctum::actingAs($user->fresh(), abilities: []);

        $this->getJson('/api/chat/tickets')->assertStatus(200);
    }
}
