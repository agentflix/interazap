<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para logar acessos negados (403 Forbidden).
 *
 * Intercepta responses 403 e registra informações detalhadas
 * no canal 'access-denied' para análise de segurança.
 *
 * @category Middleware
 */
final class LogAccessDeniedMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === Response::HTTP_FORBIDDEN) {
            $this->logAccessDenied($request, $response);
        }

        return $response;
    }

    /**
     * Log access denied details.
     */
    private function logAccessDenied(Request $request, Response $response): void
    {
        $user = $request->user();

        $reason = 'unknown';
        $content = $response->getContent();

        if ($content) {
            $decoded = json_decode($content, true);
            if (is_array($decoded)) {
                $reason = $decoded['message'] ?? $decoded['error'] ?? 'permission_denied';
            }
        }

        $tenantId = $user !== null ? $user->tenant_id : $request->attributes->get('tenant_id');

        Log::channel('access-denied')->warning('access.denied', [
            'user_id' => $user?->id,
            'tenant_id' => $tenantId,
            'resource' => $request->path(),
            'action' => $request->method(),
            'reason' => $reason,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'route_name' => $request->route()?->getName(),
            'route_action' => $request->route()?->getActionName(),
        ]);
    }
}
