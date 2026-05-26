<?php

declare(strict_types=1);

namespace Domain\Auth\Actions;

use Domain\Auth\DTOs\AuthAuthenticatedUserDTO;
use Domain\Auth\DTOs\AuthLoginDTO;
use Domain\Auth\DTOs\AuthLoginTwoFactorDTO;
use Domain\Auth\DTOs\AuthMenuItemDTO;
use Domain\Auth\DTOs\AuthSessionDTO;
use Domain\Auth\DTOs\AuthTwoFactorChallengeDTO;
use Domain\Auth\Models\AuthUser;
use Domain\Auth\Repositories\AuthUserRepository;
use Domain\Auth\Services\AuthRecoveryCodeService;
use Domain\Auth\Services\AuthTotpService;
use Domain\Platform\Services\PlatformPlanEnforcementService;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Ações de Autenticação e Sessão.
 *
 * Regras de negócio responsáveis por processar o login, logout,
 * validação de dois fatores ("2FA") e gerenciamento da sessão do usuário.
 *
 * @category Actions
 */
final class AuthLoginActions
{
    private const int LOGIN_MAX_ATTEMPTS = 5;

    private const int LOGIN_DECAY_SECONDS = 15 * 60;

    private const int SECONDS_PER_MINUTE = 60;

    public function __construct(
        private readonly AuthUserRepository $authUserRepository,
        private readonly PlatformPlanEnforcementService $planEnforcementService,
        private readonly AuthRecoveryCodeService $authRecoveryCodeService,
    ) {}

    /**
     * Realizar a tentativa de login inicial.
     *
     * Verifica as credenciais do usuário. Se o "2FA" estiver habilitado,
     * retorna um desafio de segunda etapa; caso contrário, cria e retorna
     * a sessão autenticada.
     *
     * @param  AuthLoginDTO  $dto  Dados de login contendo email e senha.
     * @return AuthSessionDTO|AuthTwoFactorChallengeDTO Sessão válida ou desafio 2FA.
     *
     * @throws ValidationException Caso as credenciais sejam inválidas.
     */
    public function login(AuthLoginDTO $dto): AuthSessionDTO|AuthTwoFactorChallengeDTO
    {
        $this->ensureLoginIsNotRateLimited($dto->email);

        $user = $this->authUserRepository->findByEmail($dto->email);

        if ($user === null || ! Hash::check($dto->password, $user->password)) {
            $this->incrementLoginAttempts($dto->email);

            throw ValidationException::withMessages([
                'email' => ['As credenciais fornecidas estão incorretas.'],
            ]);
        }

        // SEC-001: Block inactive users from logging in
        if (! $user->is_active) {
            $this->incrementLoginAttempts($dto->email);

            Log::warning('auth.login.inactive_user_attempt', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
            ]);

            throw ValidationException::withMessages([
                'email' => ['Esta conta está desativada.'],
            ]);
        }

        $this->clearLoginAttempts($dto->email);

        if ($user->two_factor_enabled) {
            Log::info('auth.login.2fa_challenge', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
            ]);

            return new AuthTwoFactorChallengeDTO($user->email);
        }

        $session = $this->createSession($user, includeToken: true, deviceName: $dto->deviceName);

        Log::info('auth.login.success', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'two_factor' => false,
        ]);

        return $session;
    }

    /**
     * Confirmar login através de autenticação de dois fatores.
     *
     * Valida o código de recuperação ou OTP e, em caso de sucesso,
     * finaliza o processo de autenticação criando a sessão.
     *
     * @param  AuthLoginTwoFactorDTO  $dto  DTO contendo email e código de verificação.
     * @return AuthSessionDTO Sessão autenticada completa.
     *
     * @throws ValidationException Caso o código seja inválido ou o usuário não tenha 2FA ativo.
     */
    public function loginWithTwoFactor(AuthLoginTwoFactorDTO $dto): AuthSessionDTO
    {
        $user = $this->authUserRepository->findByEmail($dto->email);

        if ($user === null || ! $user->two_factor_enabled) {
            throw ValidationException::withMessages([
                'code' => ['Usuário não encontrado ou 2FA não habilitado.'],
            ]);
        }

        // SEC-001: Block inactive users from logging in with 2FA
        if (! $user->is_active) {
            Log::warning('auth.login.inactive_user_2fa_attempt', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
            ]);

            throw ValidationException::withMessages([
                'code' => ['Esta conta está desativada.'],
            ]);
        }

        $totpService = app(AuthTotpService::class);
        $isValidTotp = $this->isValidTotpCode($user, $dto->code, $totpService);

        if (! $isValidTotp) {
            if (! $this->authRecoveryCodeService->validate($dto->code, $user)) {
                throw ValidationException::withMessages([
                    'code' => ['Código de verificação inválido.'],
                ]);
            }

            $this->authRecoveryCodeService->invalidate($dto->code, $user);
            $this->authUserRepository->save($user);
        }

        $session = $this->createSession($user, includeToken: true);

        Log::info('auth.login.success', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'two_factor' => true,
        ]);

        return $session;
    }

    /** Valida o codigo TOTP descriptografando o secret armazenado. */
    private function isValidTotpCode(AuthUser $user, string $code, AuthTotpService $totpService): bool
    {
        try {
            if (empty($user->two_factor_secret)) {
                return false;
            }

            $secret = Crypt::decryptString((string) $user->two_factor_secret);

            return $totpService->verify($secret, $code);
        } catch (DecryptException) {
            return false;
        }
    }

    /**
     * Obter os dados da sessão do usuário atual.
     *
     * Retorna as informações do usuário logado e suas permissões,
     * sem gerar um novo token de acesso.
     *
     * @param  AuthUser  $user  O usuário autenticado.
     * @return AuthSessionDTO DTO contendo dados do usuário e permissões.
     */
    public function me(AuthUser $user): AuthSessionDTO
    {
        return $this->createSession($user, includeToken: false);
    }

    /**
     * Encerrar a sessão atual do usuário (Logout).
     *
     * Revoga o token de acesso que está sendo utilizado na requisição atual.
     *
     * @param  AuthUser  $user  O usuário que está realizando logout.
     */
    public function logout(AuthUser $user): void
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();
        if ($token !== null) {
            $token->delete();
        }
    }

    /**
     * Renovar o token de acesso.
     *
     * Revoga o token atual e emite um novo token válido para o usuário,
     * retornando a nova sessão.
     *
     * @param  AuthUser  $user  O usuário solicitante.
     * @return AuthSessionDTO Nova sessão contendo o fresh token.
     */
    public function refresh(AuthUser $user): AuthSessionDTO
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();
        if ($token !== null) {
            $token->delete();
        }

        return $this->createSession($user, includeToken: true);
    }

    /**
     * Encerrar a impersonação e retornar à sessão do super admin original.
     *
     * Identifica se o token atual é de impersonação, revoga-o e cria
     * uma nova sessão para o super admin original.
     *
     * @param  AuthUser  $user  O usuário autenticado (super admin original).
     * @return AuthSessionDTO Nova sessão do super admin original.
     *
     * @throws ValidationException Caso o token atual não seja de impersonação.
     */
    public function stopImpersonating(AuthUser $user): AuthSessionDTO
    {
        /** @var \Laravel\Sanctum\PersonalAccessToken|null $token */
        $token = $user->currentAccessToken();

        if ($token === null || ! str_starts_with((string) $token->name, 'impersonation')) {
            throw ValidationException::withMessages([
                'session' => ['Você não está em uma sessão de impersonação.'],
            ]);
        }

        $token->delete();

        Log::info('auth.impersonate.stop', [
            'user_id' => $user->id,
            'email' => $user->email,
        ]);

        return $this->createSession($user, includeToken: true);
    }

    /**
     * Construir o menu de navegação do usuário.
     *
     * Gera a árvore de menus e filtra os itens baseado nas permissões
     * atribuídas ao usuário (RBAC).
     *
     * @param  AuthUser  $user  O usuário para o qual o menu será gerado.
     * @return array<int, AuthMenuItemDTO> Lista de itens de menu permitidos.
     */
    public function getMenu(AuthUser $user): array
    {
        $permissions = $this->resolvePermissions($user);
        $items = $this->buildBaseMenu();

        return $this->filterMenuByPermissions($items, $permissions);
    }

    /**
     * Constrói o objeto de sessão padronizado com permissões e plano do tenant.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  bool  $includeToken  Se deve emitir novo Sanctum token.
     * @param  string|null  $deviceName  Nome do dispositivo para o token.
     * @return AuthSessionDTO Sessão completa pronta para resposta.
     */
    public function createSession(AuthUser $user, bool $includeToken, ?string $deviceName = null): AuthSessionDTO
    {
        $tokenName = $deviceName ?? 'auth-token';
        $token = $includeToken ? $user->createToken($tokenName)->plainTextToken : null;
        $permissions = $this->resolvePermissions($user);
        $tenantPlan = null;

        if ($user->tenant_id) {
            $plan = $this->planEnforcementService->getCurrentPlan((string) $user->tenant_id);
            if ($plan) {
                $tenantPlan = [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'slug' => $plan->slug,
                    'ai_enabled' => (bool) $plan->ai_enabled,
                ];
            }
        }

        return new AuthSessionDTO(
            user: AuthAuthenticatedUserDTO::fromModel($user),
            permissions: $permissions,
            tenantPlan: $tenantPlan,
            token: $token,
        );
    }

    /**
     * Resolver a lista plana de permissões do usuário.
     *
     * @return array<int, string>
     */
    private function resolvePermissions(AuthUser $user): array
    {
        return $user->getAllPermissions()->pluck('name')->toArray();
    }

    /**
     * Definir a estrutura base do menu do sistema.
     *
     * @return array<int, AuthMenuItemDTO>
     */
    private function buildBaseMenu(): array
    {
        return [
            new AuthMenuItemDTO('Dashboard', '/dashboard', 'LayoutDashboard'),
            new AuthMenuItemDTO(
                label: 'Chat',
                route: '/chat',
                icon: 'MessageSquare',
                permission: 'chat.called.view',
                children: [
                    new AuthMenuItemDTO(
                        label: 'Atendimentos',
                        route: '/chat/calleds',
                        permission: 'chat.called.view',
                    ),
                    new AuthMenuItemDTO(
                        label: 'Canais',
                        route: '/chat/channels',
                        permission: 'chat.channel.view',
                    ),
                ],
            ),
            new AuthMenuItemDTO(
                label: 'CRM',
                route: '/crm',
                icon: 'Users',
                permission: 'crm.contact.view',
                children: [
                    new AuthMenuItemDTO('Contatos', '/crm/contacts', permission: 'crm.contact.view'),
                    new AuthMenuItemDTO('Negociações', '/crm/negotiations', permission: 'crm.negotiation.view'),
                    new AuthMenuItemDTO('Empresas', '/crm/companies', permission: 'crm.company.view'),
                ],
            ),
            new AuthMenuItemDTO('Relatórios', '/reports', 'BarChart3', 'reports.view'),
            new AuthMenuItemDTO(
                label: 'Financeiro',
                route: '/financial',
                icon: 'CreditCard',
                permission: 'billing.view',
                children: [
                    new AuthMenuItemDTO('Faturas', '/financial/invoices', permission: 'billing.view'),
                ],
            ),
            new AuthMenuItemDTO(
                label: 'Configurações',
                route: '/settings',
                icon: 'Settings',
                permission: 'settings.general.view',
                children: [
                    new AuthMenuItemDTO('Usuários', '/settings/users', permission: 'users.user.view'),
                    new AuthMenuItemDTO('Perfis de Acesso', '/platform/roles', permission: 'users.role.manage'),
                    new AuthMenuItemDTO('Departamentos', '/settings/departments', permission: 'departments.department.view'),
                    new AuthMenuItemDTO('Planos', '/settings/plans', permission: 'billing.view'),
                    new AuthMenuItemDTO('Governança de Prompts', '/platform/ai-governance', permission: 'ai.prompts.manage'),
                    new AuthMenuItemDTO('Tags', '/settings/tags', permission: 'settings.tags.manage'),
                ],
            ),
        ];
    }

    /**
     * Filtrar recursivamente o menu baseado em permissões.
     *
     * @param  array<int, AuthMenuItemDTO>  $items
     * @param  array<int, string>  $permissions
     * @return array<int, AuthMenuItemDTO>
     */
    private function filterMenuByPermissions(array $items, array $permissions): array
    {
        $filtered = [];

        foreach ($items as $item) {
            $children = $this->filterMenuByPermissions($item->children, $permissions);
            $hasItemPermission = $item->permission === null || in_array($item->permission, $permissions, true);

            if (! $hasItemPermission && $children === []) {
                continue;
            }

            $filtered[] = $children === $item->children
                ? $item
                : $item->withChildren($children);
        }

        return $filtered;
    }

    /** Lança ValidationException 429 se o e-mail estiver bloqueado por rate limit. */
    private function ensureLoginIsNotRateLimited(string $email): void
    {
        $key = $this->loginThrottleKey($email);

        if (! RateLimiter::tooManyAttempts($key, self::LOGIN_MAX_ATTEMPTS)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);
        $minutes = (int) max(1, ceil($seconds / self::SECONDS_PER_MINUTE));

        Log::warning('auth.login.lockout', [
            'email' => $email,
            'remaining_seconds' => $seconds,
        ]);

        $exception = ValidationException::withMessages([
            'email' => [trans('auth.throttle', ['seconds' => $seconds, 'minutes' => $minutes])],
        ]);

        $exception->status = 429;

        throw $exception;
    }

    /** Registra uma tentativa falha de login para o e-mail informado. */
    private function incrementLoginAttempts(string $email): void
    {
        RateLimiter::hit($this->loginThrottleKey($email), self::LOGIN_DECAY_SECONDS);
    }

    /** Limpa o contador de tentativas de login após autenticação bem-sucedida. */
    private function clearLoginAttempts(string $email): void
    {
        RateLimiter::clear($this->loginThrottleKey($email));
    }

    /** Retorna a chave de rate-limiter para o e-mail informado. */
    private function loginThrottleKey(string $email): string
    {
        return 'auth-login:'.Str::lower($email);
    }
}
