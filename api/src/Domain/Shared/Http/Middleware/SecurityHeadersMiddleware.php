<?php

declare(strict_types=1);

namespace Domain\Shared\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para adição de headers de segurança em todas as respostas.
 *
 * Protege contra vulnerabilidades web comuns:
 * - XSS (X-XSS-Protection, X-Content-Type-Options)
 * - Clickjacking (X-Frame-Options)
 * - Vazamento de informações (Referrer-Policy)
 * - Downgrade de protocolo (Strict-Transport-Security, somente em produção)
 */
final class SecurityHeadersMiddleware
{
    /**
     * Headers de segurança adicionados a todas as respostas.
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
     * Adiciona os headers de segurança à resposta HTTP.
     *
     * @param  Request  $request  Requisição HTTP.
     * @param  Closure  $next  Próximo middleware na cadeia.
     * @return Response Resposta com headers de segurança aplicados.
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
     * Verifica se o header HSTS deve ser adicionado.
     *
     * O HSTS é adicionado somente em produção para evitar problemas
     * com desenvolvimento local via HTTP.
     *
     * @return bool Verdadeiro somente no ambiente de produção.
     */
    private function shouldAddHsts(): bool
    {
        return app()->environment('production');
    }
}
