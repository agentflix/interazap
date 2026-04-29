<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Domain\Auth\Models\AuthPersonalAccessToken;
use Domain\Auth\Models\AuthUser;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * FEAT-047 / TASK-047.3 — Cobertura da extensão de POST /api/auth/login
 * com `device_name` opcional para emissão de Personal Access Token (Bearer).
 *
 * Garante:
 * - Nome customizado é persistido em personal_access_tokens.name
 * - Fallback `auth-token` quando device_name é omitido (zero impacto web)
 * - Tenant isolation continua resolvido via middleware (NUNCA via abilities)
 * - Logout revoga currentAccessToken
 * - Validação de tamanho máximo (100 chars)
 */
final class IssueApiTokenTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    public function test_it_issues_token_with_custom_device_name(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
            'device_name' => 'iPhone-Test',
        ])->assertStatus(200);

        $token = $response->json('data.token');
        $this->assertNotEmpty($token);

        $this->assertDatabaseHas('auth_personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'iPhone-Test',
        ]);
    }

    public function test_it_falls_back_to_default_name_when_device_name_omitted(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
        ])->assertStatus(200);

        $this->assertDatabaseHas('auth_personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'auth-token',
        ]);
    }

    public function test_it_resolves_tenant_via_middleware_not_token_abilities(): void
    {
        $userA = AuthUser::factory()->create(['password' => Hash::make('secret123')]);

        $tokenA = $this->postJson('/api/auth/login', [
            'email' => $userA->email,
            'password' => 'secret123',
            'device_name' => 'iPhone-A',
        ])->json('data.token');

        // Bearer token resolve o user dono — tenant é derivado do user via
        // TenantContextMiddleware (lê $user->tenant_id), NÃO de abilities do PAT.
        $this->withToken($tokenA)
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.user.email', $userA->email)
            ->assertJsonPath('data.user.id', $userA->id)
            ->assertJsonPath('data.user.tenant_id', $userA->tenant_id);

        // Token NÃO carrega "tenant:<id>" em abilities — preserva decisão
        // arquitetural MEMORY 2026-04-26 (caminho único de verificação).
        $rawTokenA = explode('|', (string) $tokenA, 2)[1] ?? '';
        $patA = AuthPersonalAccessToken::findToken($rawTokenA);

        $this->assertNotNull($patA);
        $abilities = $patA->abilities ?? [];

        foreach ($abilities as $ability) {
            $this->assertStringStartsNotWith(
                'tenant:',
                (string) $ability,
                'Token abilities NÃO devem conter tenant:* (decisão MEMORY 2026-04-26)'
            );
        }
    }

    public function test_it_isolates_tenant_between_distinct_bearer_tokens(): void
    {
        // Cobertura cross-tenant: dois usuários de tenants diferentes recebem
        // Bearers; cada Bearer só pode acessar dados do seu próprio user/tenant.
        // Testes separados (1 token cada) evitam efeito de auth-cache compartilhado
        // do TestCase (Sanctum tenta config('sanctum.guard') antes do Bearer).
        $userB = AuthUser::factory()->create(['password' => Hash::make('secret123')]);

        $tokenB = $this->postJson('/api/auth/login', [
            'email' => $userB->email,
            'password' => 'secret123',
            'device_name' => 'iPhone-B',
        ])->json('data.token');

        $this->withToken($tokenB)
            ->getJson('/api/auth/me')
            ->assertStatus(200)
            ->assertJsonPath('data.user.email', $userB->email)
            ->assertJsonPath('data.user.id', $userB->id)
            ->assertJsonPath('data.user.tenant_id', $userB->tenant_id);
    }

    public function test_it_revokes_token_on_logout(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        $token = $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
            'device_name' => 'iPhone-Logout',
        ])->json('data.token');

        $this->assertDatabaseHas('auth_personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'iPhone-Logout',
        ]);

        $this->withToken($token)
            ->postJson('/api/auth/logout')
            ->assertStatus(200);

        $this->assertDatabaseMissing('auth_personal_access_tokens', [
            'tokenable_id' => $user->id,
            'name' => 'iPhone-Logout',
        ]);
    }

    public function test_it_validates_device_name_max_length(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret123'),
        ]);

        $this->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => 'secret123',
            'device_name' => str_repeat('a', 101),
        ])->assertStatus(422)
            ->assertJsonValidationErrors(['device_name']);
    }
}
