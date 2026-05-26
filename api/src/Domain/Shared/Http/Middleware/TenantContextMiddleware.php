<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Middleware;

use Closure;
use Domain\Shared\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware responsável por inicializar o contexto de tenant por requisição.
 *
 * Extrai o tenant_id do usuário autenticado ou do header X-Tenant-ID,
 * verifica inconsistências cross-tenant e propaga o contexto via TenantContext.
 * Sempre limpa o contexto ao final da requisição (via finally).
 */
final class TenantContextMiddleware
{
    private const HEADER = 'X-Tenant-ID';

    /**
     * Inicializa o contexto de tenant e propaga o identificador na resposta.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  Closure  $next  Próximo middleware na cadeia.
     * @return Response Resposta com header X-Tenant-ID quando tenant identificado.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum') ?? $request->user();
        $tenantId = is_object($user) && isset($user->tenant_id) ? (string) $user->tenant_id : null;
        $requestedTenant = $request->headers->get(self::HEADER);

        if ($tenantId !== null && $requestedTenant !== null && $tenantId !== $requestedTenant) {
            Log::warning('security.cross_tenant_request_blocked', [
                'authenticated_tenant_id' => $tenantId,
                'requested_tenant_id' => $requestedTenant,
                'path' => $request->path(),
                'user_id' => $user->id ?? null,
                'ip' => $request->ip(),
            ]);

            abort(403, 'Tenant mismatch detected.');
        }

        if ($tenantId === null && $requestedTenant !== null) {
            $tenantId = $requestedTenant;
        }

        if ($tenantId !== null) {
            $request->attributes->set('tenant_id', $tenantId);
        }

        TenantContext::set($tenantId);

        try {
            /** @var Response $response */
            $response = $next($request);

            if ($tenantId !== null) {
                $response->headers->set(self::HEADER, $tenantId);
            }

            return $response;
        } finally {
            TenantContext::clear();
        }
    }
}
