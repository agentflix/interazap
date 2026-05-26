/**
 * Representa um usuário listado nas configurações de usuários da empresa.
 *
 * Contexto: usado no módulo de Configurações (settings/users) para
 * listar e gerenciar os usuários/agentes do tenant.
 */
export interface SettingsUser {
  id: string;
  name: string;
  email: string;
  is_active: boolean;
  /** Indica se é o usuário principal (owner) do tenant. */
  is_primary_tenant_user?: boolean;
  created_at?: string | null;
  /** Perfil de acesso principal (legado, use `roles`). */
  role?: string | null;
  /** Lista de perfis de acesso atribuídos ao usuário. */
  roles?: string[];
}

/**
 * Filtro de status para listagem de usuários nas configurações.
 */
export type SettingsUserStatus = 'all' | 'active' | 'inactive';

/**
 * Resposta paginada da API para listagem de usuários de configurações.
 */
export interface PaginatedSettingsUsers {
  data: SettingsUser[];
  meta: {
    current_page: number;
    total: number;
    per_page: number;
    last_page: number;
  };
}

/**
 * Payload para criação de um novo usuário/agente no tenant.
 */
export interface CreateSettingsUserPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation?: string;
  roles?: string[];
  /** Perfil de acesso principal (legado). */
  role?: string | null;
  is_active?: boolean;
  tenant_id?: string;
}

/**
 * Payload para atualização de um usuário/agente existente no tenant.
 */
export interface UpdateSettingsUserPayload {
  name: string;
  email: string;
  /** Nova senha (null = manter a atual). */
  password?: string | null;
  password_confirmation?: string;
  roles?: string[];
  role?: string | null;
  is_active?: boolean;
  tenant_id?: string;
}

/**
 * Filtros para listagem de usuários nas configurações.
 */
export interface SettingsUsersFilters {
  search?: string;
  status?: SettingsUserStatus;
  page?: number;
  per_page?: number;
}
