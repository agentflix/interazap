import type { PaginatedResponse } from '@core/models/pagination.model';

/**
 * Representa uma etapa de um funil de vendas do CRM.
 *
 * Contexto: etapas são os estágios pelos quais uma negociação avança
 * dentro de um funil (ex: Prospecção, Qualificação, Proposta, Fechamento).
 */
export interface FunnelStep {
  id: string | number;
  funnel_id?: string | number;
  crm_negotiation_funnel_id?: string | number;
  name: string;
  /** Posição da etapa na ordem do funil (começa em 1). */
  order: number;
  /** Cor de identificação visual da etapa (hex ou nome). */
  color?: string | null;
  is_active?: boolean;
  created_at?: string;
  updated_at?: string;
}

/**
 * Representa um funil de vendas do CRM.
 *
 * Contexto: usado no módulo CRM para organizar negociações em estágios.
 * O funil padrão é o ponto de entrada de novas negociações.
 */
export interface Funnel {
  id: string | number;
  name: string;
  description?: string;
  is_active: boolean;
  /** Indica se este é o funil padrão da empresa. */
  is_default?: boolean;
  steps?: FunnelStep[];
  /** Contagem de etapas (sem carregar as etapas completas). */
  steps_count?: number;
  created_at?: string;
  updated_at?: string;
}

/**
 * Filtros para listagem de funis de vendas.
 */
export interface FunnelFilters {
  search?: string;
  is_active?: boolean | string;
  per_page?: number;
  page?: number;
  sort_by?: string;
  sort_dir?: string;
}

/**
 * Resposta paginada da API para listagem de funis.
 */
export interface FunnelListResponse extends PaginatedResponse<Funnel> {
  success: boolean;
}

/**
 * Resposta da API para um único funil de vendas.
 */
export interface FunnelResponse {
  success: boolean;
  data: Funnel;
}

/**
 * Payload para criação ou atualização de um funil de vendas.
 */
export interface FunnelPayload {
  name: string;
  description?: string;
  is_active?: boolean;
  steps?: FunnelStepPayload[];
}

/**
 * Payload para criação ou atualização de uma etapa de funil.
 */
export interface FunnelStepPayload {
  name: string;
  order?: number;
  color?: string;
  is_active?: boolean;
}
