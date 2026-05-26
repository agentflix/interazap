<?php

declare(strict_types=1);

namespace Domain\Ai\Policies;

use Domain\Auth\Models\AuthUser;

/**
 * Policy para autorização de operações em Agentes de IA.
 *
 * Aplica fallback automático: a permissão de gerenciamento (manage)
 * concede também acesso de visualização (view), simplificando a configuração
 * de permissões para administradores do tenant.
 */
final class AiAgentPolicy
{
    /**
     * Verifica se o usuário pode listar agentes.
     */
    public function viewAny(AuthUser $user): bool
    {
        return $this->canWithManageFallback($user, 'ai.autopilots.view');
    }

    /**
     * Verifica se o usuário pode visualizar um agente específico.
     */
    public function view(AuthUser $user): bool
    {
        return $this->canWithManageFallback($user, 'ai.autopilots.view');
    }

    /**
     * Verifica se o usuário pode criar um agente.
     */
    public function create(AuthUser $user): bool
    {
        return $this->canWithManageFallback($user, 'ai.autopilots.manage');
    }

    /**
     * Verifica se o usuário pode atualizar um agente.
     */
    public function update(AuthUser $user): bool
    {
        return $this->canWithManageFallback($user, 'ai.autopilots.manage');
    }

    /**
     * Verifica se o usuário pode excluir um agente.
     */
    public function delete(AuthUser $user): bool
    {
        return $this->canWithManageFallback($user, 'ai.autopilots.manage');
    }

    private function canWithManageFallback(AuthUser $user, string $permission): bool
    {
        return $user->can($permission) || $user->can('ai.autopilots.manage');
    }
}
