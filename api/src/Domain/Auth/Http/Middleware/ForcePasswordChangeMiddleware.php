<?php

declare(strict_types=1);

namespace Domain\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bloqueia acesso a rotas protegidas quando o usuário tem troca de senha obrigatória pendente.
 *
 * Permite apenas a rota /auth/force-password-change e as rotas de logout/me
 * enquanto o flag force_password_change estiver ativo.
 */
final class ForcePasswordChangeMiddleware
{
    /**
     * Rotas que podem ser acessadas mesmo com troca de senha pendente.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = [
        'api/auth/force-password-change',
        'api/auth/profile/password',
        'api/auth/logout',
        'api/auth/me',
    ];

    /**
     * Processa a requisição, bloqueando acesso se troca de senha estiver pendente.
     *
     * Retorna 403 com code FORCE_PASSWORD_CHANGE para rotas nao permitidas.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum') ?? $request->user();

        if (! $user) {
            return $next($request);
        }

        $forceChange = isset($user->force_password_change)
            ? (bool) $user->force_password_change
            : false;

        if (! $forceChange) {
            return $next($request);
        }

        $path = ltrim($request->path(), '/');

        foreach (self::ALLOWED_ROUTES as $allowed) {
            if ($path === $allowed || str_starts_with($path, $allowed)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Password change required before proceeding.',
            'code' => 'FORCE_PASSWORD_CHANGE',
        ], 403);
    }
}
