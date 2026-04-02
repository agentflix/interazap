<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware for adding security headers to all responses.
 *
 * Adds protection against common web vulnerabilities:
 * - XSS attacks (X-XSS-Protection, X-Content-Type-Options)
 * - Clickjacking (X-Frame-Options)
 * - Information leakage (Referrer-Policy)
 * - Protocol downgrade attacks (Strict-Transport-Security)
 */
final class SecurityHeadersMiddleware
{
    /**
     * Security headers to add to all responses.
     *
     * @var array<string, string>
     */
    private const array SECURITY_HEADERS = [
        'X-Content-Type-Options' => 'nosniff',
        'X-Frame-Options' => 'DENY',
        'X-XSS-Protection' => '1; mode=block',
        'Referrer-Policy' => 'strict-origin-when-cross-origin',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        // Add security headers
        foreach (self::SECURITY_HEADERS as $header => $value) {
            $response->headers->set($header, $value);
        }

        // Add HSTS only in production (when using HTTPS)
        if ($this->shouldAddHsts()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }

    /**
     * Determine if HSTS header should be added.
     *
     * Only add HSTS in production environment to avoid issues
     * with local development over HTTP.
     */
    private function shouldAddHsts(): bool
    {
        return app()->environment('production');
    }
}
