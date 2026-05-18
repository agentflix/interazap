import type { Funnel, FunnelStep } from '@core/models/funnel.model';

/** Status possíveis de uma negociação. */
export type NegotiationStatus = 'open' | 'won' | 'lost';

/** Resumo do contato em uma negociação. */
export interface NegotiationContactSummary {
  id: string | number;
  name: string;
}

/** Resumo da empresa em uma negociação. */
export interface NegotiationCompanySummary {
  id: string | number;
  name: string;
  address?: string | null;
  city?: string | null;
  state?: string | null;
  zip_code?: string | null;
  phone?: string | null;
}

/** Resumo do usuário em uma negociação. */
export interface NegotiationUserSummary {
  id: string | number;
  name: string;
}

/** Modelo de negociação do CRM. */
export interface Negotiation {
  id: string | number;
  title: string;
  value?: number;
  amount?: number;
  status: NegotiationStatus;
  position?: number;
  expected_close_date?: string;
  notes?: string;
  contact_id?: string | number;
  crm_company_id?: string | number;
  funnel_id?: string | number;
  step_id?: string | number;
  user_id?: string | number;
  auth_user_id?: string | number;
  contact?: NegotiationContactSummary;
  crm_company?: NegotiationCompanySummary;
  company?: NegotiationCompanySummary;
  step?: FunnelStep;
  funnel?: Funnel;
  user?: NegotiationUserSummary;
  created_at?: string;
  updated_at?: string;
}

/** Payload para criação ou atualização de negociação. */
export interface NegotiationPayload {
  title: string;
  funnel_id: string | number;
  step_id: string | number;
  contact_id: string | number;
  crm_company_id: string | number;
  user_id?: string | number;
  value?: number;
  expected_close_date?: string;
  notes?: string;
  status?: NegotiationStatus;
}

/** Filtros para listagem de negociações. */
export interface NegotiationFilters {
  search?: string;
  status?: NegotiationStatus | string | null;
  funnel_id?: string | number | null;
  step_id?: string | number | null;
  crm_company_id?: string | number | null;
  contact_id?: string | number | null;
  user_id?: string | number | null;
  date_from?: string;
  date_to?: string;
  expected_close_from?: string;
  expected_close_to?: string;
  amount_min?: number;
  amount_max?: number;
  lead_score_min?: number;
  lead_score_max?: number;
  tag_ids?: (string | number)[];
  reason_loss_id?: string | number;
  has_pending_tasks?: boolean;
  product_id?: string | number;
  per_page?: number;
  page?: number;
}

/** Filtros para visualização Kanban de negociações. */
export interface NegotiationKanbanFilters {
  status?: NegotiationStatus | string | null;
  search?: string;
  funnel_id?: string | number | null;
  step_id?: string | number | null;
  crm_company_id?: string | number | null;
  contact_id?: string | number | null;
  user_id?: string | number | null;
  date_from?: string;
  date_to?: string;
  expected_close_from?: string;
  expected_close_to?: string;
  amount_min?: number;
  amount_max?: number;
  lead_score_min?: number;
  lead_score_max?: number;
  tag_ids?: (string | number)[];
  reason_loss_id?: string | number;
  has_pending_tasks?: boolean;
  product_id?: string | number;
}

/** Etapa do funil com negociações no contexto Kanban. */
export interface NegotiationKanbanStep extends FunnelStep {
  negotiations?: Negotiation[];
  /** Total de negociações na etapa (independente da página). */
  total_count?: number;
  /** Soma dos valores de todas as negociações da etapa. */
  total_value?: number;
  /** Se há mais negociações além das retornadas na página inicial. */
  has_more?: boolean;
  /** Cursor para buscar a próxima página desta etapa. */
  next_cursor?: string | null;
}

/** Resposta da API para uma página de cursor de uma etapa. */
export interface KanbanStepPage {
  negotiations: Negotiation[];
  has_more: boolean;
  next_cursor: string | null;
}

/** Item de produto em uma negociação. */
export interface NegotiationProductItem {
  id: string | number;
  negotiation_id: string | number;
  product_id: string | number;
  quantity: number;
  price: number;
  discount?: number | null;
  discount_type?: string | null;
  subtotal?: number;
  discount_value?: number;
  total?: number;
  product?: {
    id: string | number;
    name: string;
    price?: number | null;
  } | null;
}

/** Payload para criação ou atualização de produto em negociação. */
export interface NegotiationProductPayload {
  product_id?: string | number;
  crm_product_id?: string | number;
  name?: string;
  quantity?: number;
  price?: number;
  unit_price?: number;
  discount?: number;
}
