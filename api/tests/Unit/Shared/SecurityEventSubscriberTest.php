<?php

declare(strict_types=1);

namespace Tests\Unit\Shared;

use Domain\Auth\Models\AuthUser;
use Domain\Shared\Events\TokenCreated;
use Domain\Shared\Events\TokenRevoked;
use Domain\Shared\Events\TwoFactorDisabled;
use Domain\Shared\Events\TwoFactorEnabled;
use Domain\Shared\Listeners\SecurityEventSubscriber;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

class SecurityEventSubscriberTest extends TestCase
{
    private function makeRequest(): Request
    {
        return Request::create('/', 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'Pest Test Agent',
        ]);
    }

    public function test_logs_all_security_events(): void
    {
        $subscriber = new SecurityEventSubscriber($this->makeRequest());
        $user = AuthUser::factory()->make(['email' => 'security@example.com']);

        Log::shouldReceive('channel')->times(4)->with('auth')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('auth.2fa.enabled', Mockery::type('array'));
        Log::shouldReceive('warning')->once()->with('auth.2fa.disabled', Mockery::type('array'));
        Log::shouldReceive('info')->once()->with('auth.token.created', Mockery::on(fn (array $context): bool => $context['token_name'] === 'api-token' && $context['token_id'] === 'token-1'));
        Log::shouldReceive('info')->once()->with('auth.token.revoked', Mockery::on(fn (array $context): bool => $context['token_id'] === 'token-1'));

        $subscriber->handleTwoFactorEnabled(new TwoFactorEnabled($user));
        $subscriber->handleTwoFactorDisabled(new TwoFactorDisabled($user));
        $subscriber->handleTokenCreated(new TokenCreated($user, 'api-token', 'token-1'));
        $subscriber->handleTokenRevoked(new TokenRevoked($user, 'token-1'));
    }

    public function test_returns_event_subscription_map(): void
    {
        $subscriber = new SecurityEventSubscriber($this->makeRequest());

        $map = $subscriber->subscribe(app('events'));

        $this->assertSame('handleTwoFactorEnabled', $map[TwoFactorEnabled::class]);
        $this->assertSame('handleTwoFactorDisabled', $map[TwoFactorDisabled::class]);
        $this->assertSame('handleTokenCreated', $map[TokenCreated::class]);
        $this->assertSame('handleTokenRevoked', $map[TokenRevoked::class]);
    }
}
