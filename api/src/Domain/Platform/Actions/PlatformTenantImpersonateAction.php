<?php

declare(strict_types=1);

namespace Domain\Platform\Actions;

use Domain\Auth\Actions\AuthLoginActions;
use Domain\Auth\Models\AuthRole;
use Domain\Auth\Models\AuthUser;
use Domain\Platform\Models\PlatformTenant;
use Domain\Shared\Scopes\TenantScope;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Ação de impersonação de tenant para super admins.
 *
 * Valida a senha do super admin, busca o primeiro usuário Gerente ativo do tenant
 * e cria uma sessão autenticada em nome desse usuário.
 */
final class PlatformTenantImpersonateAction
{
    public function __construct(private readonly AuthLoginActions $authLoginActions) {}

    /**
     * Executar a impersonação do tenant.
     *
     * @param  PlatformTenant  $tenant  Tenant alvo da impersonação.
     * @param  AuthUser  $superAdmin  Super admin que está solicitando a impersonação.
     * @param  string  $password  Senha do super admin para validação.
     * @return array<string, mixed> Sessão do usuário admin do tenant com metadados de impersonação.
     *
     * @throws ValidationException Caso a senha seja inválida, tenant inativo ou sem usuário Gerente.
     */
    public function execute(PlatformTenant $tenant, AuthUser $superAdmin, string $password): array
    {
        $this->validatePassword($superAdmin, $password);
        $this->validateTenant($tenant);

        $targetUser = $this->resolveTargetUser($tenant);

        if ($targetUser === null) {
            throw ValidationException::withMessages([
                'tenant' => ['Este tenant não possui usuários administradores ativos.'],
            ]);
        }

        $session = $this->authLoginActions->createSession($targetUser, includeToken: true, deviceName: 'impersonation');

        return [
            ...$session->toArray(),
            'is_impersonating' => true,
            'impersonated_by' => [
                'id' => (string) $superAdmin->id,
                'name' => (string) $superAdmin->name,
                'email' => (string) $superAdmin->email,
            ],
            'impersonated_tenant' => [
                'id' => (string) $tenant->id,
                'name' => (string) $tenant->name,
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
     * Validar que o tenant pode ser impersonado.
     *
     * @throws ValidationException
     */
    private function validateTenant(PlatformTenant $tenant): void
    {
        if (! $tenant->is_active) {
            throw ValidationException::withMessages([
                'tenant' => ['Não é possível impersonar um tenant inativo.'],
            ]);
        }

        $hasUsers = AuthUser::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->exists();

        if (! $hasUsers) {
            throw ValidationException::withMessages([
                'tenant' => ['Esta empresa não possui usuários. Impossível impersonar.'],
            ]);
        }
    }

    /**
     * Resolver o usuário alvo da impersonação.
     *
     * Busca o primeiro usuário ativo do tenant com role 'Gerente'.
     */
    private function resolveTargetUser(PlatformTenant $tenant): ?AuthUser
    {
        return AuthUser::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereHas('roles', function ($query): void {
                $query->where('id', AuthRole::GERENTE_ID);
            })
            ->first();
    }
}
