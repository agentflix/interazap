import type { PaginatedResponse } from '@core/models/pagination.model';

/**
 * Representa um motivo de perda de negociação no CRM.
 *
 * Contexto: usado ao encerrar uma negociação como "perdida".
 * Permite análise dos motivos de perda nos relatórios do CRM.
 */
export interface ReasonLoss {
  id: string;
  company_id: string;
  name: string;
  description?: string;
  is_active: boolean;
  /** Indica se ao selecionar este motivo é obrigatório adicionar um comentário. */
  requires_comment: boolean;
  /** Posição de exibição na lista de motivos. */
  position?: number;
  /** Quantidade de vezes que este motivo foi usado em negociações. */
  usage_count?: number;
  created_at: string;
  updated_at: string;
}

/**
 * Filtros para listagem de motivos de perda.
 */
export interface ReasonLossFilters {
  search?: string;
  active?: boolean;
  is_active?: boolean;
  per_page?: number;
  page?: number;
  sort_by?: string;
  sort_dir?: string;
}

/**
 * Resposta paginada da API para listagem de motivos de perda.
 */
export interface ReasonLossListResponse extends PaginatedResponse<ReasonLoss> {
  success: boolean;
}

/**
 * Resposta da API para um único motivo de perda.
 */
export interface ReasonLossResponse {
  success: boolean;
  data: ReasonLoss;
}

/**
 * Resposta da API para busca individual de motivo de perda (sem success flag).
 */
export interface ReasonLossItemResponse {
  data: ReasonLoss;
}
