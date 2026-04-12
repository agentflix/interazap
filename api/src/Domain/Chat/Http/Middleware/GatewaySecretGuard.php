<?php

declare(strict_types=1);

namespace Domain\Chat\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de autorização para rotas acessadas pelo Gateway.
 *
 * Valida o token GATEWAY_SECRET no header Authorization: Bearer <token>.
 * Usado para proteger endpoints que são acessados exclusivamente pelo
 * Gateway (ex: lookup de phone_number_id para normalização de webhooks).
 *
 * @category Middleware
 */
final class GatewaySecretGuard
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $secret = config('services.gateway.secret');

        if (! is_string($secret) || $secret === '') {
            abort(500, 'GATEWAY_SECRET not configured');
        }

        $authHeader = $request->header('Authorization');

        if (! is_string($authHeader) || ! str_starts_with($authHeader, 'Bearer ')) {
            abort(401, 'Missing or invalid Authorization header');
        }

        $token = substr($authHeader, 7);

        if (! hash_equals($secret, $token)) {
            abort(403, 'Invalid gateway secret');
        }

        return $next($request);
    }
}
