<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware de autenticação para rotas internas via API key.
 *
 * Verifica o header X-Internal-Api-Key usando comparação segura contra
 * timing attacks. Rejeita com 401 quando a chave está ausente ou incorreta.
 */
final class InternalApiKeyMiddleware
{
    private ?string $cachedKey = null;

    /**
     * Valida a API key interna no header da requisição.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  Closure(Request):Response  $next  Próximo middleware na cadeia.
     * @return Response Resposta 401 se a chave for inválida, ou prossegue para o próximo middleware.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $provided = (string) $request->header('X-Internal-Api-Key', '');
        $expected = $this->cachedKey ??= (string) config('services.gateway.api_key', '');

        if ($expected === '' || ! hash_equals($expected, $provided)) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Unauthorized internal request.',
            ], 401);
        }

        return $next($request);
    }
}
