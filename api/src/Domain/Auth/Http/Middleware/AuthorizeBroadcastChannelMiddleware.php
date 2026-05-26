<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Middleware;

use Closure;
use Domain\Chat\Models\ChatTicket;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Valida isolamento de tenant para autenticação de canais privados mobile.
 */
final class AuthorizeBroadcastChannelMiddleware
{
    /**
     * Processa a requisição verificando isolamento de tenant para canais privados.
     *
     * Aborta com 403 se o canal private-tenant.{id} não corresponder ao tenant
     * do usuário autenticado, ou se o canal private-ticket.{id} não pertencer ao mesmo tenant.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum') ?? $request->user();
        $channelName = (string) $request->input('channel_name', '');

        if ($user === null || $channelName === '') {
            return $next($request);
        }

        if (str_starts_with($channelName, 'private-tenant.')) {
            $tenantId = substr($channelName, strlen('private-tenant.'));

            abort_if((string) $user->tenant_id !== $tenantId, 403);
        }

        if (str_starts_with($channelName, 'private-ticket.')) {
            $ticketId = substr($channelName, strlen('private-ticket.'));
            $allowed = ChatTicket::query()
                ->where('id', $ticketId)
                ->where('tenant_id', $user->tenant_id)
                ->exists();

            abort_if(! $allowed, 403);
        }

        return $next($request);
    }
}
