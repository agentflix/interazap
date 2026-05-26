<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware que garante que apenas super admins da plataforma acessem a rota.
 *
 * Retorna HTTP 403 se o usuário autenticado não for super admin.
 */
final class EnsurePlatformSuperAdmin
{
    /**
     * Verifica se o usuário é super admin e permite ou rejeita a requisição.
     *
     * @param  Request  $request  Requisição HTTP atual.
     * @param  Closure(Request): Response  $next  Próximo middleware na pilha.
     * @return Response Resposta da requisição ou JSON 403.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        if (! is_object($user) || ! method_exists($user, 'isSuperAdmin') || $user->isSuperAdmin() !== true) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Forbidden platform admin request.',
            ], 403);
        }

        return $next($request);
    }
}
