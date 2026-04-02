<?php

declare(strict_types=1);

namespace Tests\Unit\Auth;

use Domain\Auth\Actions\AuthLoginActions;
use Domain\Auth\DTOs\AuthLoginDTO;
use Domain\Auth\DTOs\AuthLoginTwoFactorDTO;
use Domain\Auth\DTOs\AuthSessionDTO;
use Domain\Auth\DTOs\AuthTwoFactorChallengeDTO;
use Domain\Auth\Models\AuthPermission;
use Domain\Auth\Models\AuthUser;
use Domain\Auth\Repositories\EloquentAuthUserRepository;
use Domain\Auth\Services\AuthRecoveryCodeService;
use Domain\Auth\Services\AuthTotpService;
use Domain\Platform\Services\PlatformPlanEnforcementService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AuthLoginActionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
    }

    private function createActions(): AuthLoginActions
    {
        return new AuthLoginActions(
            new EloquentAuthUserRepository,
            new PlatformPlanEnforcementService,
            new AuthRecoveryCodeService,
        );
    }

    public function test_login_returns_session_when_2fa_disabled(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret'),
            'two_factor_enabled' => false,
        ]);

        $actions = $this->createActions();
        $session = $actions->login(AuthLoginDTO::fromArray([
            'email' => $user->email,
            'password' => 'secret',
            'remember' => false,
        ]));

        $this->assertInstanceOf(AuthSessionDTO::class, $session);
        $this->assertNotEmpty($session->token);
        $this->assertSame($user->email, $session->user->email);
    }

    public function test_login_returns_two_factor_challenge_when_enabled(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret'),
            'two_factor_enabled' => true,
        ]);

        $actions = $this->createActions();
        $response = $actions->login(AuthLoginDTO::fromArray([
            'email' => $user->email,
            'password' => 'secret',
            'remember' => false,
        ]));

        $this->assertInstanceOf(AuthTwoFactorChallengeDTO::class, $response);
        $this->assertSame($user->email, $response->email);
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret'),
        ]);

        $actions = $this->createActions();

        $this->expectException(ValidationException::class);

        $actions->login(AuthLoginDTO::fromArray([
            'email' => $user->email,
            'password' => 'wrong',
            'remember' => false,
        ]));
    }

    public function test_login_with_two_factor_removes_used_code(): void
    {
        $codes = ['ABC123', 'XYZ789'];
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret'),
            'two_factor_enabled' => true,
            'two_factor_recovery_codes' => json_encode($codes),
        ]);

        $actions = $this->createActions();
        $session = $actions->loginWithTwoFactor(AuthLoginTwoFactorDTO::fromArray([
            'email' => $user->email,
            'code' => 'ABC123',
        ]));

        $this->assertInstanceOf(AuthSessionDTO::class, $session);
        $this->assertNotEmpty($session->token);

        $user->refresh();
        $remaining = json_decode((string) $user->two_factor_recovery_codes, true);
        $this->assertSame(['XYZ789'], $remaining);
    }

    public function test_login_with_two_factor_rejects_invalid_code(): void
    {
        $user = AuthUser::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_recovery_codes' => json_encode(['ABC123']),
        ]);

        $actions = $this->createActions();

        $this->expectException(ValidationException::class);

        $actions->loginWithTwoFactor(AuthLoginTwoFactorDTO::fromArray([
            'email' => $user->email,
            'code' => 'INVALID',
        ]));
    }

    public function test_login_with_two_factor_accepts_totp_code(): void
    {
        $totpService = app(AuthTotpService::class);
        $secret = $totpService->generateSecret();

        $user = AuthUser::factory()->create([
            'two_factor_enabled' => true,
            'two_factor_secret' => Crypt::encryptString($secret),
            'two_factor_recovery_codes' => json_encode(['ABC123']),
        ]);

        $actions = $this->createActions();
        $session = $actions->loginWithTwoFactor(AuthLoginTwoFactorDTO::fromArray([
            'email' => $user->email,
            'code' => $totpService->currentCode($secret),
        ]));

        $this->assertInstanceOf(AuthSessionDTO::class, $session);
        $this->assertNotEmpty($session->token);

        $user->refresh();
        $remaining = json_decode((string) $user->two_factor_recovery_codes, true);
        $this->assertSame(['ABC123'], $remaining);
    }

    public function test_logout_revokes_current_token(): void
    {
        $user = AuthUser::factory()->create();
        $token = $user->createToken('auth-token');
        $user->withAccessToken($token->accessToken);

        $actions = $this->createActions();
        $actions->logout($user);

        $this->assertDatabaseMissing('auth_personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_refresh_replaces_current_token(): void
    {
        $user = AuthUser::factory()->create();
        $token = $user->createToken('auth-token');
        $user->withAccessToken($token->accessToken);

        $actions = $this->createActions();
        $session = $actions->refresh($user);

        $this->assertInstanceOf(AuthSessionDTO::class, $session);
        $this->assertNotEmpty($session->token);
        $this->assertDatabaseMissing('auth_personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    /**
     * SEC-001: Inactive users must be blocked from logging in.
     */
    public function test_login_rejects_inactive_user(): void
    {
        $user = AuthUser::factory()->create([
            'password' => Hash::make('secret'),
            'is_active' => false,
        ]);

        $actions = $this->createActions();

        $this->expectException(ValidationException::class);

        $actions->login(AuthLoginDTO::fromArray([
            'email' => $user->email,
            'password' => 'secret',
            'remember' => false,
        ]));
    }

    public function test_get_menu_filters_items_by_permissions(): void
    {
        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'crm.contact.view', 'guard_name' => 'sanctum'],
            ['id' => (string) \Illuminate\Support\Str::orderedUuid()]
        );

        $user = AuthUser::factory()->create();
        $user->givePermissionTo($permission);
        $user->refresh();

        $actions = $this->createActions();
        $menu = $actions->getMenu($user);

        $routes = collect($menu)->flatMap(function ($item): array {
            $children = collect($item->children)->map(fn ($child): string => $child->route)->all();

            return array_merge([$item->route], $children);
        })->all();

        $this->assertContains('/crm', $routes);
        $this->assertContains('/crm/contacts', $routes);
        $this->assertNotContains('/chat', $routes);
        $this->assertNotContains('/financial', $routes);
    }

    public function test_get_menu_keeps_parent_when_child_permission_is_allowed(): void
    {
        $permission = AuthPermission::query()->firstOrCreate(
            ['name' => 'users.role.manage', 'guard_name' => 'sanctum'],
            ['id' => (string) \Illuminate\Support\Str::orderedUuid()]
        );

        $user = AuthUser::factory()->create();
        $user->givePermissionTo($permission);
        $user->refresh();

        $actions = $this->createActions();
        $menu = $actions->getMenu($user);

        $settingsItem = collect($menu)->first(fn ($item): bool => $item->route === '/settings');

        $this->assertNotNull($settingsItem);
        $childRoutes = collect($settingsItem->children)->map(fn ($child): string => $child->route)->all();
        $this->assertContains('/platform/roles', $childRoutes);
    }
}
