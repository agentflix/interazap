<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * TASK-013-S1-SEC / API-SEC-006
 * Garante que rotas públicas de proposta retornam 429 após exceder o rate limit.
 */
final class CrmProposalRateLimitTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    protected function tearDown(): void
    {
        // Restaura rate limiter ao padrão após o teste
        RateLimiter::for('public', static fn (\Illuminate\Http\Request $request): Limit => Limit::perMinute(30)->by($request->ip() ?: 'public'));
        parent::tearDown();
    }

    /**
     * Rotas públicas de proposta devem retornar 429 após exceder o rate limit.
     * Usa limit de 1 req/min para tornar o teste rápido e determinístico.
     */
    public function test_public_proposals_rate_limit_returns_429_after_30_requests_per_minute(): void
    {
        // Override do rate limiter para facilitar o teste (limit de 1 por minuto)
        RateLimiter::for('public', static fn (\Illuminate\Http\Request $request): Limit => Limit::perMinute(1)->by($request->ip() ?: 'public'));

        // Primeiro request — dentro do limite (pode retornar qualquer status HTTP válido exceto 429)
        $first = $this->getJson('/api/crm/proposals/view/token-inexistente-test');
        $this->assertNotSame(429, $first->status(), 'O primeiro request não deve ser throttled.');

        // Segundo request — excede o limite → deve retornar 429
        $second = $this->getJson('/api/crm/proposals/view/token-inexistente-test');
        $second->assertStatus(429);
    }

    /**
     * Rota de accept de proposta também deve ser protegida pelo rate limiter.
     */
    public function test_public_proposals_accept_route_is_rate_limited(): void
    {
        RateLimiter::for('public', static fn (\Illuminate\Http\Request $request): Limit => Limit::perMinute(1)->by($request->ip() ?: 'public'));

        // Primeiro request (limit de 1)
        $first = $this->postJson('/api/crm/proposals/token-inexistente/accept', []);
        $this->assertNotSame(429, $first->status());

        // Segundo request — excede o limite
        $second = $this->postJson('/api/crm/proposals/token-inexistente/accept', []);
        $second->assertStatus(429);
    }

    /**
     * Rota de reject de proposta também deve ser protegida pelo rate limiter.
     */
    public function test_public_proposals_reject_route_is_rate_limited(): void
    {
        RateLimiter::for('public', static fn (\Illuminate\Http\Request $request): Limit => Limit::perMinute(1)->by($request->ip() ?: 'public'));

        // Primeiro request
        $first = $this->postJson('/api/crm/proposals/token-inexistente/reject', []);
        $this->assertNotSame(429, $first->status());

        // Segundo request — excede o limite
        $second = $this->postJson('/api/crm/proposals/token-inexistente/reject', []);
        $second->assertStatus(429);
    }
}
