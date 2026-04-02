<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class InternalApiKeyMiddleware
{
    private ?string $cachedKey = null;

    /**
     * @param  Closure(Request):Response  $next
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
