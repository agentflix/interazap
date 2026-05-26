<?php

declare(strict_types=1);

namespace Domain\Shared\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Platform\Exceptions\PlanLimitExceededException;
use Domain\Platform\Services\PlatformPlanEnforcementService;
use Domain\Shared\Models\SharedMedia;

/**
 * Policy de controle de acesso para mídia compartilhada.
 *
 * Verifica permissões de criação, visualização, atualização e exclusão de
 * arquivos de mídia, respeitando limites de armazenamento do plano vigente.
 */
final class SharedMediaPolicy
{
    public function __construct(
        private readonly PlatformPlanEnforcementService $enforcementService,
    ) {}

    /**
     * Verifica se o usuário pode fazer upload de nova mídia.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  SharedMedia|null  $media  Instância de mídia (não utilizada na criação).
     * @return bool Verdadeiro se permitido.
     *
     * @throws \Domain\Platform\Exceptions\PlanLimitExceededException Se o limite de armazenamento do plano foi atingido.
     */
    public function create(AuthUser $user, ?SharedMedia $media = null): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $tenantId = (string) $user->tenant_id;
        if ($tenantId === '') {
            return false;
        }

        if (! $this->enforcementService->canUploadFile($tenantId)) {
            throw PlanLimitExceededException::forResource('storage');
        }

        return true;
    }

    /**
     * Verifica se o usuário pode listar mídias do tenant.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @return bool Verdadeiro se tiver tenant_id válido ou for super admin.
     */
    public function viewAny(AuthUser $user): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (string) $user->tenant_id !== '';
    }

    /**
     * Verifica se o usuário pode visualizar uma mídia específica.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  SharedMedia  $media  Mídia a visualizar.
     * @return bool Verdadeiro se a mídia pertence ao mesmo tenant do usuário.
     */
    public function view(AuthUser $user, SharedMedia $media): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (string) $user->tenant_id !== '' && $media->tenant_id === $user->tenant_id;
    }

    /**
     * Verifica se o usuário pode atualizar uma mídia específica.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  SharedMedia  $media  Mídia a atualizar.
     * @return bool Verdadeiro se a mídia pertence ao mesmo tenant do usuário.
     */
    public function update(AuthUser $user, SharedMedia $media): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (string) $user->tenant_id !== '' && $media->tenant_id === $user->tenant_id;
    }

    /**
     * Verifica se o usuário pode excluir uma mídia específica.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  SharedMedia  $media  Mídia a excluir.
     * @return bool Verdadeiro se a mídia pertence ao mesmo tenant do usuário.
     */
    public function delete(AuthUser $user, SharedMedia $media): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (string) $user->tenant_id !== '' && $media->tenant_id === $user->tenant_id;
    }
}
