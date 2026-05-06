<?php

declare(strict_types=1);

namespace Domain\Platform\Actions;

use Domain\Auth\Actions\AuthLoginActions;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Scopes\TenantScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Ação de impersonação de usuário específico para super admins.
 *
 * Valida a senha do super admin, verifica se o usuário alvo está ativo
 * e se seu tenant está ativo, então cria uma sessão autenticada em nome desse usuário.
 */
final class PlatformUserImpersonateAction
{
    public function __construct(
        private readonly AuthLoginActions $authLoginActions,
    ) {}

    /**
     * Executar a impersonação do usuário.
     *
     * @param  AuthUser  $targetUser  Usuário a ser impersonado (com tenant carregado).
     * @param  AuthUser  $superAdmin  Super admin que está solicitando a impersonação.
     * @param  string  $password  Senha do super admin para validação.
     * @return array<string, mixed> Sessão do usuário alvo com metadados de impersonação.
     *
     * @throws ValidationException Caso a senha seja inválida, usuário inativo ou tenant inativo.
     */
    public function execute(AuthUser $targetUser, AuthUser $superAdmin, string $password): array
    {
        $this->validatePassword($superAdmin, $password);
        $this->validateNotSelfImpersonation($superAdmin, $targetUser);
        $this->validateTargetUser($targetUser);

        $session = $this->authLoginActions->createSession(
            $targetUser,
            includeToken: true,
            deviceName: 'impersonation',
        );

        return [
            ...$session->toArray(),
            'is_impersonating' => true,
            'impersonated_by' => [
                'id' => (string) $superAdmin->id,
                'name' => (string) $superAdmin->name,
                'email' => (string) $superAdmin->email,
            ],
            'impersonated_tenant' => [
                'id' => (string) $targetUser->tenant_id,
                'name' => (string) $targetUser->tenant?->name,
            ],
        ];
    }

    /**
     * Validar senha do super admin.
     *
     * @throws ValidationException
     */
    private function validatePassword(AuthUser $superAdmin, string $password): void
    {
        if (! Hash::check($password, $superAdmin->password)) {
            throw ValidationException::withMessages([
                'password' => ['As credenciais fornecidas estão incorretas.'],
            ]);
        }
    }

    /**
     * Impedir que o super admin se impersonate a si mesmo.
     *
     * @throws ValidationException
     */
    private function validateNotSelfImpersonation(AuthUser $superAdmin, AuthUser $targetUser): void
    {
        if ($superAdmin->id === $targetUser->id) {
            throw ValidationException::withMessages([
                'user' => ['Você não pode impersonar a si mesmo.'],
            ]);
        }
    }

    /**
     * Validar que o usuário pode ser impersonado.
     *
     * Verifica se o usuário está ativo e se seu tenant está ativo.
     *
     * @throws ValidationException
     */
    private function validateTargetUser(AuthUser $targetUser): void
    {
        if (! $targetUser->is_active) {
            throw ValidationException::withMessages([
                'user' => ['Não é possível impersonar um usuário inativo.'],
            ]);
        }

        $tenant = $this->getTenant($targetUser);

        if ($tenant === null || ! $tenant->is_active) {
            throw ValidationException::withMessages([
                'user' => ['Não é possível impersonar um usuário de tenant inativo.'],
            ]);
        }
    }

    /**
     * Obter o tenant do usuário sem ser afetado pelo TenantScope.
     *
     * Usa withoutGlobalScope para bypassar o filtro automático de tenant
     * que seria aplicado ao chamar $user->tenant em contexto de super admin
     * autenticado de outro tenant.
     */
    private function getTenant(AuthUser $user): ?PlatformTenant
    {
        return PlatformTenant::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('id', $user->tenant_id)
            ->first();
    }
}
