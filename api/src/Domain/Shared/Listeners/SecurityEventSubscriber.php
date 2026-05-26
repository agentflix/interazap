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
     * Registra log de habilitação de 2FA no canal 'auth'.
     *
     * @param  TwoFactorEnabled  $event  Evento de habilitação de 2FA.
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
     * Registra log de desabilitação de 2FA no canal 'auth' com nível warning.
     *
     * @param  TwoFactorDisabled  $event  Evento de desabilitação de 2FA.
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
     * Registra log de criação de token de acesso no canal 'auth'.
     *
     * @param  TokenCreated  $event  Evento de criação de token.
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
     * Registra log de revogação de token de acesso no canal 'auth'.
     *
     * @param  TokenRevoked  $event  Evento de revogação de token.
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
     * Registra os listeners de segurança no dispatcher de eventos.
     *
     * @param  Dispatcher  $events  Dispatcher de eventos do Laravel.
     * @return array<class-string, string> Mapa de evento para nome do método handler.
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
