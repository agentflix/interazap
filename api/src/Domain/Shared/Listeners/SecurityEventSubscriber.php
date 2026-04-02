<?php

declare(strict_types=1);

namespace Domain\Shared\Listeners;

use Domain\Shared\Events\TokenCreated;
use Domain\Shared\Events\TokenRevoked;
use Domain\Shared\Events\TwoFactorDisabled;
use Domain\Shared\Events\TwoFactorEnabled;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Subscriber para eventos de segurança relacionados a 2FA e tokens.
 *
 * Registra no canal 'auth' eventos de habilitação/desabilitação de 2FA
 * e criação/revogação de tokens de acesso.
 *
 * @category Listeners
 */
final class SecurityEventSubscriber
{
    public function __construct(
        private readonly Request $request,
    ) {}

    /**
     * Handle 2FA enabled event.
     */
    public function handleTwoFactorEnabled(TwoFactorEnabled $event): void
    {
        Log::channel('auth')->info('auth.2fa.enabled', [
            'user_id' => $event->user->id,
            'tenant_id' => $event->user->tenant_id,
            'email' => $event->user->email,
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }

    /**
     * Handle 2FA disabled event.
     */
    public function handleTwoFactorDisabled(TwoFactorDisabled $event): void
    {
        Log::channel('auth')->warning('auth.2fa.disabled', [
            'user_id' => $event->user->id,
            'tenant_id' => $event->user->tenant_id,
            'email' => $event->user->email,
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }

    /**
     * Handle token created event.
     */
    public function handleTokenCreated(TokenCreated $event): void
    {
        Log::channel('auth')->info('auth.token.created', [
            'user_id' => $event->user->id,
            'tenant_id' => $event->user->tenant_id,
            'token_name' => $event->tokenName,
            'token_id' => $event->tokenId,
            'ip' => $this->request->ip(),
            'user_agent' => $this->request->userAgent(),
        ]);
    }

    /**
     * Handle token revoked event.
     */
    public function handleTokenRevoked(TokenRevoked $event): void
    {
        Log::channel('auth')->info('auth.token.revoked', [
            'user_id' => $event->user->id,
            'tenant_id' => $event->user->tenant_id,
            'token_id' => $event->tokenId,
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
            TwoFactorEnabled::class => 'handleTwoFactorEnabled',
            TwoFactorDisabled::class => 'handleTwoFactorDisabled',
            TokenCreated::class => 'handleTokenCreated',
            TokenRevoked::class => 'handleTokenRevoked',
        ];
    }
}
