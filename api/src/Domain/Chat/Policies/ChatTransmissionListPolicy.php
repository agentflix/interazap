<?php

declare(strict_types=1);

namespace Domain\Chat\Policies;

use Domain\Auth\Models\AuthUser;
use Domain\Chat\Models\ChatTransmissionList;

/**
 * Política de Segurança para Listas de Transmissão de Chat.
 *
 * @category Policies
 */
final class ChatTransmissionListPolicy
{
    /**
     * Determina se o usuário pode listar as listas de transmissão do tenant.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @return bool True se autorizado.
     */
    public function viewAny(AuthUser $user): bool
    {
        return $user->can('chat.transmission_lists.view');
    }

    /**
     * Determina se o usuário pode visualizar uma lista de transmissão específica.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  ChatTransmissionList  $transmissionList  Modelo da lista.
     * @return bool True se pertencer ao tenant e tiver permissão.
     */
    public function view(AuthUser $user, ChatTransmissionList $transmissionList): bool
    {
        return $transmissionList->tenant_id === $user->tenant_id
            && $user->can('chat.transmission_lists.view');
    }

    /**
     * Determina se o usuário pode criar novas listas de transmissão.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @return bool True se autorizado.
     */
    public function create(AuthUser $user): bool
    {
        return $user->can('chat.transmission_lists.create');
    }

    /**
     * Determina se o usuário pode atualizar uma lista de transmissão existente.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  ChatTransmissionList  $transmissionList  Modelo da lista.
     * @return bool True se pertencer ao tenant e tiver permissão.
     */
    public function update(AuthUser $user, ChatTransmissionList $transmissionList): bool
    {
        return $transmissionList->tenant_id === $user->tenant_id
            && $user->can('chat.transmission_lists.update');
    }

    /**
     * Determina se o usuário pode remover uma lista de transmissão.
     *
     * @param  AuthUser  $user  Usuário autenticado.
     * @param  ChatTransmissionList  $transmissionList  Modelo da lista.
     * @return bool True se pertencer ao tenant e tiver permissão.
     */
    public function delete(AuthUser $user, ChatTransmissionList $transmissionList): bool
    {
        return $transmissionList->tenant_id === $user->tenant_id
            && $user->can('chat.transmission_lists.delete');
    }
}
