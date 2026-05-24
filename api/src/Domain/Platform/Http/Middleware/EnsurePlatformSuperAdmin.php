<?php

declare(strict_types=1);

namespace Domain\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsurePlatformSuperAdmin
{
    /**
     * @param  Closure(Request): Response  $next
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
