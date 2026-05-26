import { type Company } from '@core/models/company.model';

/**
 * Representa um usuário do sistema.
 *
 * Contexto: modelo genérico usado em selects e listagens onde não se precisa
 * dos detalhes completos de `SettingsUser` ou `PlatformUser`.
 */
export interface User {
  id: string;
  name: string;
  email: string;
  /** Perfil de acesso principal (legado). */
  role?: string;
  avatar_url?: string;
  tenant_id?: string;
  department_id?: string | number;
  company_id?: string | number;
  company?: Company;
  is_active: boolean;
  /** Indica se é o usuário principal (owner) do tenant. */
  is_primary_tenant_user?: boolean;
}

/**
 * Payload para criação ou atualização de um usuário.
 */
export interface UserUpsertPayload extends Partial<User> {
  password?: string;
  password_confirmation?: string;
}

/**
 * Resposta paginada da API para listagem de usuários.
 */
export interface UserListResponse {
  data: User[];
  meta?: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
}

/**
 * Filtros para listagem de usuários.
 */
export interface UserFilters {
  search?: string | undefined;
  is_active?: boolean;
  page?: number;
  per_page?: number;
  sort_by?: string;
  sort_dir?: string;
}
