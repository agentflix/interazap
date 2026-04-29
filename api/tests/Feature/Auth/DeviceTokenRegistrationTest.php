<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Domain\Auth\Models\AuthDeviceToken;
use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Cobertura da TASK-047.10 (fatia A): persistência e API de device tokens.
 */
final class DeviceTokenRegistrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_registers_token_with_tenant_isolation(): void
    {
        $userA = AuthUser::factory()->create();
        $userB = AuthUser::factory()->create();

        Sanctum::actingAs($userA, abilities: ['*']);

        $this->postJson('/api/devices/register', [
            'platform' => 'ios',
            'token' => 'same-token-across-tenants',
            'device_name' => 'iPhone A',
        ])->assertCreated();

        Sanctum::actingAs($userB, abilities: ['*']);

        $this->postJson('/api/devices/register', [
            'platform' => 'ios',
            'token' => 'same-token-across-tenants',
            'device_name' => 'iPhone B',
        ])->assertCreated();

        $this->assertDatabaseCount('auth_device_tokens', 2);

        $this->assertDatabaseHas('auth_device_tokens', [
            'tenant_id' => $userA->tenant_id,
            'user_id' => $userA->id,
            'platform' => 'ios',
            'token' => 'same-token-across-tenants',
        ]);

        $this->assertDatabaseHas('auth_device_tokens', [
            'tenant_id' => $userB->tenant_id,
            'user_id' => $userB->id,
            'platform' => 'ios',
            'token' => 'same-token-across-tenants',
        ]);
    }

    public function test_duplicate_token_does_not_create_duplicate_and_reactivates_if_revoked(): void
    {
        $user = AuthUser::factory()->create();
        Sanctum::actingAs($user, abilities: ['*']);

        $this->postJson('/api/devices/register', [
            'platform' => 'android',
            'token' => 'android-token-1',
            'device_name' => 'Pixel 8',
        ])->assertCreated();

        /** @var AuthDeviceToken $deviceToken */
        $deviceToken = AuthDeviceToken::query()->firstOrFail();
        $deviceToken->update(['revoked_at' => now()->subMinute()]);

        $this->postJson('/api/devices/register', [
            'platform' => 'android',
            'token' => 'android-token-1',
            'device_name' => 'Pixel 8 - Reactivated',
        ])->assertOk();

        $this->assertDatabaseCount('auth_device_tokens', 1);

        $this->assertDatabaseHas('auth_device_tokens', [
            'id' => $deviceToken->id,
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'platform' => 'android',
            'token' => 'android-token-1',
            'device_name' => 'Pixel 8 - Reactivated',
            'revoked_at' => null,
        ]);
    }

    public function test_revoke_is_soft_by_filling_revoked_at(): void
    {
        $user = AuthUser::factory()->create();
        Sanctum::actingAs($user, abilities: ['*']);

        $register = $this->postJson('/api/devices/register', [
            'platform' => 'web',
            'token' => 'web-token-1',
            'device_name' => 'Chrome',
        ])->assertCreated();

        $deviceTokenId = (string) $register->json('data.id');

        $this->deleteJson('/api/devices/'.$deviceTokenId)
            ->assertNoContent();

        $this->assertDatabaseHas('auth_device_tokens', [
            'id' => $deviceTokenId,
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'platform' => 'web',
            'token' => 'web-token-1',
        ]);

        /** @var AuthDeviceToken $deviceToken */
        $deviceToken = AuthDeviceToken::query()->findOrFail($deviceTokenId);
        $this->assertNotNull($deviceToken->revoked_at);
    }

    public function test_it_does_not_allow_revoking_token_from_another_user_or_tenant(): void
    {
        $owner = AuthUser::factory()->create();
        $attacker = AuthUser::factory()->create();

        Sanctum::actingAs($owner, abilities: ['*']);

        $register = $this->postJson('/api/devices/register', [
            'platform' => 'ios',
            'token' => 'victim-token',
            'device_name' => 'Owner iPhone',
        ])->assertCreated();

        $deviceTokenId = (string) $register->json('data.id');

        Sanctum::actingAs($attacker, abilities: ['*']);

        $this->deleteJson('/api/devices/'.$deviceTokenId)
            ->assertForbidden();

        $this->assertDatabaseHas('auth_device_tokens', [
            'id' => $deviceTokenId,
            'tenant_id' => $owner->tenant_id,
            'user_id' => $owner->id,
            'token' => 'victim-token',
            'revoked_at' => null,
        ]);
    }
}
