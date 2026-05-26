/**
 * Representa um usuário no contexto administrativo da plataforma.
 *
 * Contexto: usado no painel da plataforma para gerenciar usuários
 * de todos os tenants. Inclui dados do tenant e empresa vinculados.
 */
export interface PlatformUser {
  id: string;
  name: string;
  email: string;
  /** Perfis de acesso atribuídos ao usuário. */
  roles?: string[];
  avatar_url?: string;
  /** ID do tenant ao qual o usuário pertence. */
  tenant_id?: string;
  department_id?: string | number;
  company_id?: string | number;
  is_active: boolean;
  /** Indica se o usuário deve trocar a senha no próximo acesso. */
  force_password_change?: boolean;
  /** Indica se é o usuário principal (owner) do tenant. */
  is_primary_tenant_user?: boolean;
  tenant?: {
    id: string;
    name: string;
    document?: string;
  };
  company?: {
    id: string | number;
    name: string;
    document?: string;
  };
}

/**
 * Resposta paginada da API para listagem de usuários da plataforma.
 */
export interface PlatformUserListResponse {
  data: PlatformUser[];
  meta?: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
}

/**
 * Filtros para listagem de usuários da plataforma.
 */
export interface PlatformUserFilters {
  search?: string | undefined;
  is_active?: boolean;
  page?: number;
  per_page?: number;
  sort_by?: string;
  sort_dir?: string;
  /** Filtra usuários por tenant específico. */
  tenant_id?: string;
}
