<?php

declare(strict_types=1);

namespace Domain\Shared\Listeners;

use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Subscriber para eventos de autenticação.
 *
 * Centraliza o logging de todos os eventos relacionados à autenticação,
 * registrando-os no canal 'auth' para análise de segurança.
 *
 * @category Listeners
 */
final class AuthEventSubscriber
{
    public function __construct(
        private readonly Request $request,
    ) {}

    /**
     * Handle user login events.
     */
    public function handleLogin(Login $event): void
    {
        /** @var \Domain\Auth\Models\AuthUser|null $user */
        $user = $event->user;

        if (! $user instanceof \Domain\Auth\Models\AuthUser) {
            return;
        }

        Log::channel('auth')->info('auth.login.success', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'email' => $user->email,
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'two_factor' => $user->two_factor_enabled ?? false,
            'guard' => $event->guard,
        ]);
    }

    /**
     * Handle failed login attempts.
     */
    public function handleFailed(Failed $event): void
    {
        $credentials = $event->credentials;

        Log::channel('auth')->warning('auth.login.failed', [
            'email' => $credentials['email'] ?? 'unknown',
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'guard' => $event->guard,
            'reason' => 'invalid_credentials',
        ]);
    }

    /**
     * Handle user logout events.
     */
    public function handleLogout(Logout $event): void
    {
        /** @var \Domain\Auth\Models\AuthUser|null $user */
        $user = $event->user;

        if (! $user instanceof \Domain\Auth\Models\AuthUser) {
            return;
        }

        Log::channel('auth')->info('auth.logout', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'email' => $user->email,
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
            'guard' => $event->guard,
        ]);
    }

    /**
     * Handle password reset events.
     */
    public function handlePasswordReset(PasswordReset $event): void
    {
        /** @var \Domain\Auth\Models\AuthUser|null $user */
        $user = $event->user;

        if (! $user instanceof \Domain\Auth\Models\AuthUser) {
            return;
        }

        Log::channel('auth')->info('auth.password.reset', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'email' => $user->email,
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }

    /**
     * Register the listeners for the subscriber.
     *
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'handleLogin',
            Failed::class => 'handleFailed',
            Logout::class => 'handleLogout',
            PasswordReset::class => 'handlePasswordReset',
        ];
    }
}
