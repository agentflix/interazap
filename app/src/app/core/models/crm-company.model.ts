/**
 * Representa uma empresa do CRM (diferente do tenant/Company da plataforma).
 *
 * Contexto: usado no módulo CRM para gerenciar empresas vinculadas a contatos
 * e negociações. Separado do model `Company` que representa os tenants da plataforma.
 */
export interface CRMCompany {
  id: string;
  name: string;
  /** Documento fiscal da empresa (CNPJ). */
  document?: string;
  email?: string;
  phone?: string;
  address?: string;
  city?: string;
  state?: string;
  zip_code?: string;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

/**
 * Filtros para listagem de empresas do CRM.
 */
export interface CRMCompanyFilters {
  search?: string;
  is_active?: boolean;
  per_page?: number;
  page?: number;
  sort_by?: string;
  sort_dir?: string;
}

/**
 * Payload para criação ou atualização de uma empresa do CRM.
 */
export interface CRMCompanyPayload {
  name: string;
  document?: string;
  email?: string;
  phone?: string;
  address?: string;
  city?: string;
  state?: string;
  zip_code?: string;
  is_active?: boolean;
}

/**
 * Resposta paginada da API para listagem de empresas do CRM.
 */
export interface CRMCompanyListResponse {
  success: boolean;
  data: CRMCompany[];
  meta: {
    current_page: number;
    from: number;
    last_page: number;
    per_page: number;
    to: number;
    total: number;
  };
}

/**
 * Resposta da API para uma única empresa do CRM.
 */
export interface CRMCompanyResponse {
  success: boolean;
  data: CRMCompany;
}
