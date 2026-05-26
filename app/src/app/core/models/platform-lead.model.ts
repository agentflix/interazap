/**
 * Representa um lead (contato comercial) captado pela plataforma.
 *
 * Contexto: leads são potenciais clientes que demonstraram interesse
 * no InteraZap através do site ou landing pages. Podem ser convertidos
 * em tenants pelo painel administrativo da plataforma.
 */
export interface PlatformLead {
  id: string;
  name: string;
  email: string;
  phone: string;
  company?: string | null;
  /** Status atual do lead no funil de vendas da plataforma. */
  status?: string | null;
  /** Indica se o lead deu consentimento LGPD para contato. */
  lgpd_consent: boolean;
  created_at?: string;
  updated_at?: string;
}

/**
 * Resposta paginada da API para listagem de leads da plataforma.
 */
export interface PlatformLeadListResponse {
  data: PlatformLead[];
  meta: {
    current_page: number;
    total: number;
    per_page: number;
    last_page: number;
  };
}

/**
 * Filtros para listagem de leads da plataforma.
 */
export interface PlatformLeadFilters {
  search?: string;
  status?: string;
  page?: number;
  per_page?: number;
  sort_by?: 'name' | 'email' | 'created_at';
  sort_dir?: 'asc' | 'desc';
}

/**
 * Payload para conversão de um lead em tenant da plataforma.
 *
 * Contexto: enviado ao endpoint de conversão para criar um novo tenant
 * a partir dos dados de um lead qualificado.
 */
export interface PlatformLeadConvertPayload {
  name: string;
  email: string;
  phone: string;
  document?: string | null;
  plan_id?: string | null;
}
