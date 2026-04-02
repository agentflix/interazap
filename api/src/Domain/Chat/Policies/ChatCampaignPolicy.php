<?php

declare(strict_types=1);

namespace Domain\Chat\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatCampaign;

/**
 * Política de Segurança para Campanhas de Chat.
 *
 * Define as regras de autorização para acesso e manipulação de campanhas,
 * garantindo isolamento entre tenants e verificação de permissões RBAC.
 *
 * @category Policies
 */
final class ChatCampaignPolicy
{
    /**
     * Determina se o usuário pode listar as campanhas do tenant.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @return bool True se autorizado.
     */
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('chat.campaigns.view');
    }

    /**
     * Determina se o usuário pode visualizar uma campanha específica.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  ChatCampaign  $campaign  Modelo da campanha.
     * @return bool True se pertencer ao tenant e tiver permissão.
     */
    public function view(AuthUser $user, ChatCampaign $campaign): bool
    {
        return $campaign->tenant_id === $user->tenant_id
            && $user->can('chat.campaigns.view');
    }

    /**
     * Determina se o usuário pode criar novas campanhas.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @return bool True se autorizado.
     */
    public function create(AuthUser $user): bool
    {
        return $user->can('chat.campaigns.create');
    }

    /**
     * Determina se o usuário pode atualizar uma campanha existente.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  ChatCampaign  $campaign  Modelo da campanha.
     * @return bool True se pertencer ao tenant e tiver permissão.
     */
    public function update(AuthUser $user, ChatCampaign $campaign): bool
    {
        return $campaign->tenant_id === $user->tenant_id
            && $user->can('chat.campaigns.update');
    }

    /**
     * Determina se o usuário pode remover uma campanha.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  ChatCampaign  $campaign  Modelo da campanha.
     * @return bool True se pertencer ao tenant e tiver permissão.
     */
    public function delete(AuthUser $user, ChatCampaign $campaign): bool
    {
        return $campaign->tenant_id === $user->tenant_id
            && $user->can('chat.campaigns.delete');
    }
}
