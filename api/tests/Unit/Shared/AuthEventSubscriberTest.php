<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Domain\Auth\Models\AuthUser;
use Domain\Shared\Listeners\AuthEventSubscriber;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class AuthEventSubscriberTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    private function makeRequest(): Request
    {
        return Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'Pest Test Agent',
        ]);
    }

    public function test_handles_login_event_for_auth_user(): void
    {
        $subscriber = new AuthEventSubscriber($this->makeRequest());
        $user = AuthUser::factory()->make([
            'email' => 'auth-login@example.com',
            'two_factor_enabled' => true,
        ]);

        Log::shouldReceive('channel')->once()->with('auth')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('auth.login.success', Mockery::on(fn (array $context): bool => $context['email'] === $user->email
            && $context['user_id'] === $user->id
            && $context['guard'] === 'sanctum'
            && $context['two_factor'] === true));

        $subscriber->handleLogin(new Login('sanctum', $user, false));

        $this->assertTrue(true);
    }

    public function test_ignores_login_when_user_is_not_auth_user(): void
    {
        $subscriber = new AuthEventSubscriber($this->makeRequest());

        $otherUser = Mockery::mock(Authenticatable::class);

        Log::shouldReceive('channel')->never();

        $subscriber->handleLogin(new Login('sanctum', $otherUser, false));

        $this->assertTrue(true);
    }

    public function test_handles_failed_login_event(): void
    {
        $subscriber = new AuthEventSubscriber($this->makeRequest());

        Log::shouldReceive('channel')->once()->with('auth')->andReturnSelf();
        Log::shouldReceive('warning')->once()->with('auth.login.failed', Mockery::on(fn (array $context): bool => $context['email'] === 'failed@example.com'
            && $context['reason'] === 'invalid_credentials'
            && $context['guard'] === 'sanctum'));

        $subscriber->handleFailed(new Failed('sanctum', null, ['email' => 'failed@example.com']));

        $this->assertTrue(true);
    }

    public function test_handles_logout_and_password_reset_events_for_auth_user(): void
    {
        $subscriber = new AuthEventSubscriber($this->makeRequest());
        $user = AuthUser::factory()->make(['email' => 'auth-logout@example.com']);

        Log::shouldReceive('channel')->twice()->with('auth')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('auth.logout', Mockery::type('array'));
        Log::shouldReceive('info')->once()->with('auth.password.reset', Mockery::type('array'));

        $subscriber->handleLogout(new Logout('sanctum', $user));
        $subscriber->handlePasswordReset(new PasswordReset($user));

        $this->assertTrue(true);
    }

    public function test_returns_event_subscription_map(): void
    {
        $subscriber = new AuthEventSubscriber($this->makeRequest());

        $map = $subscriber->subscribe(app('events'));

        $this->assertSame('handleLogin', $map[Login::class]);
        $this->assertSame('handleFailed', $map[Failed::class]);
        $this->assertSame('handleLogout', $map[Logout::class]);
        $this->assertSame('handlePasswordReset', $map[PasswordReset::class]);
    }
}
