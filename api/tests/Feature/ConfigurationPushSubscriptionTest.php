<?php

declare(strict_types=1);

namespace Tests\Feature;

use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfigurationPushSubscriptionTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_user_can_subscribe_and_unsubscribe_push_endpoint(): void
    {
        $user = AuthUser::factory()->create();
        Sanctum::actingAs($user, abilities: ['*']);

        $endpoint = 'https://push.example.dev/subscriptions/abc';

        $this->postJson('/api/notifications/push-subscribe', [
            'endpoint' => $endpoint,
            'keys' => [
                'p256dh' => 'p256dh-key',
                'auth' => 'auth-key',
            ],
            'content_encoding' => 'aes128gcm',
        ])
            ->assertOk()
            ->assertJsonPath('data.endpoint', $endpoint)
            ->assertJsonPath('data.is_active', true);

        $this->assertDatabaseHas('configuration_push_subscriptions', [
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'is_active' => 1,
        ]);

        $this->deleteJson('/api/notifications/push-subscribe', [
            'endpoint' => $endpoint,
        ])
            ->assertOk();

        $this->assertDatabaseHas('configuration_push_subscriptions', [
            'tenant_id' => $user->tenant_id,
            'user_id' => $user->id,
            'endpoint' => $endpoint,
            'is_active' => 0,
        ]);
    }
}
