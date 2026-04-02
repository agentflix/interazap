<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use Domain\Auth\Models\AuthUser;
use Domain\Shared\Events\TokenCreated;
use Domain\Shared\Events\TokenRevoked;
use Domain\Shared\Events\TwoFactorDisabled;
use Domain\Shared\Events\TwoFactorEnabled;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AuthAuditLoggingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_success_logs_to_default_channel(): void
    {
        Log::spy();

        $user = AuthUser::factory()->create([
            'password' => Hash::make('password123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertOk();

        // The AuthLoginActions already logs auth.login.success
        Log::shouldHaveReceived('info')
            ->withArgs(fn (string $message, array $context): bool => $message === 'auth.login.success'
                && $context['user_id'] === $user->id
                && $context['tenant_id'] === $user->tenant_id
                && $context['two_factor'] === false
            );
    }

    public function test_login_inactive_user_logs_warning(): void
    {
        Log::spy();

        $user = AuthUser::factory()->create([
            'password' => Hash::make('password123'),
            'is_active' => false,
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertUnprocessable();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message): bool => $message === 'auth.login.inactive_user_attempt');
    }

    public function test_two_factor_enabled_event_can_be_dispatched(): void
    {
        Event::fake([TwoFactorEnabled::class]);

        $user = AuthUser::factory()->create();

        event(new \Domain\Shared\Events\TwoFactorEnabled($user));

        Event::assertDispatched(TwoFactorEnabled::class, fn (TwoFactorEnabled $event): bool => $event->user->id === $user->id);
    }

    public function test_two_factor_disabled_event_can_be_dispatched(): void
    {
        Event::fake([TwoFactorDisabled::class]);

        $user = AuthUser::factory()->create();

        event(new \Domain\Shared\Events\TwoFactorDisabled($user));

        Event::assertDispatched(TwoFactorDisabled::class, fn (TwoFactorDisabled $event): bool => $event->user->id === $user->id);
    }

    public function test_token_created_event_can_be_dispatched(): void
    {
        Event::fake([TokenCreated::class]);

        $user = AuthUser::factory()->create();

        event(new \Domain\Shared\Events\TokenCreated($user, 'test-token', 'token-123'));

        Event::assertDispatched(TokenCreated::class, fn (TokenCreated $event): bool => $event->user->id === $user->id
            && $event->tokenName === 'test-token'
            && $event->tokenId === 'token-123');
    }

    public function test_token_revoked_event_can_be_dispatched(): void
    {
        Event::fake([TokenRevoked::class]);

        $user = AuthUser::factory()->create();

        event(new \Domain\Shared\Events\TokenRevoked($user, 'token-123'));

        Event::assertDispatched(TokenRevoked::class, fn (TokenRevoked $event): bool => $event->user->id === $user->id
            && $event->tokenId === 'token-123');
    }

    public function test_security_subscriber_is_registered(): void
    {
        $events = app('events');

        // Check that security events have listeners
        $listeners = $events->getListeners(TwoFactorEnabled::class);

        $this->assertNotEmpty($listeners, 'TwoFactorEnabled event should have listeners registered');
    }

    public function test_two_factor_enabled_listener_exists(): void
    {
        $events = app('events');
        $listeners = $events->getListeners(TwoFactorEnabled::class);

        $this->assertNotEmpty($listeners);
    }

    public function test_two_factor_disabled_listener_exists(): void
    {
        $events = app('events');
        $listeners = $events->getListeners(TwoFactorDisabled::class);

        $this->assertNotEmpty($listeners);
    }

    public function test_token_created_listener_exists(): void
    {
        $events = app('events');
        $listeners = $events->getListeners(TokenCreated::class);

        $this->assertNotEmpty($listeners);
    }

    public function test_token_revoked_listener_exists(): void
    {
        $events = app('events');
        $listeners = $events->getListeners(TokenRevoked::class);

        $this->assertNotEmpty($listeners);
    }
}
