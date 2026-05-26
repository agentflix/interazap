/**
 * Representa um perfil de acesso (role) no sistema de controle de permissões.
 *
 * Contexto: usado no módulo de Configurações para gerenciar papéis e permissões
 * atribuídos aos usuários. Cada role agrupa um conjunto de permissões.
 */
export interface Role {
  id: string;
  name: string;
  /** Lista de permissões atribuídas a este perfil (ex: 'chat.called.view'). */
  permissions: string[];
  /** Quantidade total de permissões atribuídas. */
  permissions_count: number;
  /** Quantidade de usuários com este perfil atribuído. */
  users_count: number;
  created_at: string;
  updated_at: string;
}

/**
 * Permissões agrupadas por módulo/grupo.
 *
 * Contexto: usado na tela de edição de perfil para exibir as permissões
 * organizadas por módulo (chat, crm, billing, etc.).
 *
 * @example
 * ```typescript
 * const grouped: GroupedPermissions = {
 *   'chat': ['chat.called.view', 'chat.called.manage'],
 *   'crm': ['crm.contact.view', 'crm.contact.manage']
 * };
 * ```
 */
export type GroupedPermissions = Record<string, string[]>;

/**
 * Payload para criação ou atualização de um perfil de acesso.
 */
export interface RolePayload {
  name: string;
  /** Lista de permissões a atribuir ao perfil. */
  permissions: string[];
}

/**
 * Representa um usuário listado no contexto de um perfil de acesso.
 *
 * Contexto: exibido na tela de detalhes de um perfil para mostrar
 * quais usuários possuem aquele perfil atribuído.
 */
export interface RoleUser {
  id: string;
  name: string;
  email: string;
  phone?: string;
  avatar_url?: string;
  is_active: boolean;
  roles: string[];
  created_at: string;
  updated_at: string;
}

/**
 * Resposta paginada da API para listagem de usuários de um perfil.
 */
export interface PaginatedRoleUsers {
  data: RoleUser[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

/**
 * Filtros para listagem de perfis de acesso.
 */
export interface RoleFilters {
  search?: string;
  page?: number;
  per_page?: number;
}

/**
 * Filtros para listagem de usuários por perfil de acesso.
 */
export interface RoleUsersFilters {
  search?: string;
  page?: number;
  per_page?: number;
}

/**
 * Resposta paginada da API para listagem de perfis de acesso.
 */
export interface PaginatedRoles {
  data: Role[];
  meta: {
    current_page: number;
    total: number;
    per_page: number;
    last_page: number;
  };
}

/**
 * Envelope genérico de resposta da API com indicador de sucesso.
 *
 * @template T Tipo do dado retornado no campo `data`.
 */
export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}
