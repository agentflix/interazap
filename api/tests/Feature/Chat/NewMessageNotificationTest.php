<?php

declare(strict_types=1);

namespace Tests\Feature\Chat;

use Domain\Auth\Models\AuthDeviceToken;
use Domain\Auth\Models\AuthUser;
use Domain\Chat\Events\MessagePersisted;
use Domain\Chat\Listeners\MessagePersistorListener;
use Domain\Chat\Listeners\RevokeInvalidPushTokenListener;
use Domain\Chat\Notifications\NewMessageNotification;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\Events\NotificationFailed;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use NotificationChannels\Apn\ApnChannel;
use NotificationChannels\Fcm\FcmChannel;
use Tests\TestCase;

final class NewMessageNotificationTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_fans_out_only_to_tenant_users(): void
    {
        Queue::fake();
        Notification::fake();

        $tenantUserA = AuthUser::factory()->create();
        $tenantUserB = AuthUser::factory()->create([
            'tenant_id' => $tenantUserA->tenant_id,
        ]);
        $otherTenantUser = AuthUser::factory()->create();

        $this->createDeviceToken($tenantUserA, 'ios', 'ios-token-a');
        $this->createDeviceToken($tenantUserB, 'android', 'android-token-b');
        $this->createDeviceToken($otherTenantUser, 'ios', 'ios-token-other');

        app(MessagePersistorListener::class)->handle(new MessagePersisted(
            tenantId: (string) $tenantUserA->tenant_id,
            ticketId: (string) Str::orderedUuid(),
            body: 'Mensagem recebida do contato',
        ));

        Notification::assertSentTo($tenantUserA, NewMessageNotification::class);
        Notification::assertSentTo($tenantUserB, NewMessageNotification::class);
        Notification::assertNotSentTo($otherTenantUser, NewMessageNotification::class);
    }

    public function test_it_skips_revoked_tokens(): void
    {
        Queue::fake();
        Notification::fake();

        $user = AuthUser::factory()->create();

        $this->createDeviceToken(
            user: $user,
            platform: 'ios',
            token: 'revoked-ios-token',
            revokedAt: now()->subMinute(),
        );

        app(MessagePersistorListener::class)->handle(new MessagePersisted(
            tenantId: (string) $user->tenant_id,
            ticketId: (string) Str::orderedUuid(),
            body: 'Mensagem recebida do contato',
        ));

        Notification::assertNotSentTo($user, NewMessageNotification::class);
    }

    public function test_it_sends_apn_payload_for_ios_and_fcm_for_android(): void
    {
        $user = AuthUser::factory()->create();

        $this->createDeviceToken($user, 'ios', 'ios-token');
        $this->createDeviceToken($user, 'android', 'android-token');

        $notification = new NewMessageNotification(
            tenantId: (string) $user->tenant_id,
            ticketId: (string) Str::orderedUuid(),
            body: 'Nova mensagem de teste',
        );

        $channels = $notification->via($user);

        $this->assertContains(ApnChannel::class, $channels);
        $this->assertContains(FcmChannel::class, $channels);
    }

    public function test_it_revokes_token_on_apns_invalid_token_response(): void
    {
        $user = AuthUser::factory()->create();
        $deviceToken = $this->createDeviceToken($user, 'ios', 'invalid-ios-token');

        $listener = new RevokeInvalidPushTokenListener;

        $listener->handle(new NotificationFailed(
            notifiable: $user,
            notification: new NewMessageNotification(
                tenantId: (string) $user->tenant_id,
                ticketId: (string) Str::orderedUuid(),
                body: 'Nova mensagem',
            ),
            channel: ApnChannel::class,
            data: [
                'token' => 'invalid-ios-token',
                'message' => 'BadDeviceToken',
            ],
        ));

        $deviceToken->refresh();

        $this->assertNotNull($deviceToken->revoked_at);
    }

    private function createDeviceToken(
        AuthUser $user,
        string $platform,
        string $token,
        ?\DateTimeInterface $revokedAt = null,
    ): AuthDeviceToken {
        /** @var AuthDeviceToken $deviceToken */
        $deviceToken = AuthDeviceToken::query()->create([
            'tenant_id' => (string) $user->tenant_id,
            'user_id' => (string) $user->id,
            'platform' => $platform,
            'token' => $token,
            'device_name' => 'Test Device',
            'last_active_at' => now(),
            'revoked_at' => $revokedAt,
        ]);

        return $deviceToken;
    }
}
