/**
 * Representa um tenant/empresa na plataforma.
 *
 * Contexto: usado no módulo de Plataforma para listar e gerenciar
 * os tenants cadastrados pelos administradores da plataforma.
 */
export interface Company {
  id: string | number;
  name: string;
  /** ID do segmento de mercado ao qual a empresa pertence. */
  segment_id?: string | null;
  segment_name?: string | null;
  /** Documento fiscal da empresa (CNPJ). */
  document?: string;
  email?: string;
  phone?: string;
  street?: string;
  number?: string;
  complement?: string;
  district?: string;
  address?: string;
  city?: string;
  state?: string;
  zip?: string;
  zip_code?: string;
  zipcode?: string;
  /** ID do plano contratado pelo tenant. */
  plan_id?: string | null;
  is_active: boolean;
  /** Código único do tenant na plataforma. */
  tenant_code?: string;
  primary_email?: string;
  created_at?: string;
  deleted_at?: string;
}

/**
 * Filtros para listagem de tenants/empresas da plataforma.
 *
 * Contexto: parâmetros aceitos pelo endpoint administrativo de listagem de tenants.
 */
export interface CompanyFilters {
  search?: string;
  is_active?: boolean;
  /** Inclui registros excluídos logicamente (soft deleted). */
  trashed?: boolean;
  created_from?: string;
  created_to?: string;
  per_page?: number;
  page?: number;
  sort_by?: 'name' | 'document' | 'is_active' | 'created_at';
  sort_dir?: 'asc' | 'desc';
  plan_id?: string | number;
}
