import type { Funnel, FunnelStep } from '@core/models/funnel.model';

/**
 * Status possíveis de uma negociação do CRM.
 *
 * - `open`: negociação em andamento
 * - `won`: negociação ganha/fechada
 * - `lost`: negociação perdida
 */
export type NegotiationStatus = 'open' | 'won' | 'lost';

/**
 * Resumo do contato vinculado a uma negociação.
 */
export interface NegotiationContactSummary {
  id: string | number;
  name: string;
}

/**
 * Resumo da empresa vinculada a uma negociação.
 */
export interface NegotiationCompanySummary {
  id: string | number;
  name: string;
  address?: string | null;
  city?: string | null;
  state?: string | null;
  zip_code?: string | null;
  phone?: string | null;
}

/**
 * Resumo do usuário/vendedor responsável por uma negociação.
 */
export interface NegotiationUserSummary {
  id: string | number;
  name: string;
}

/**
 * Representa uma negociação do CRM (deal/oportunidade).
 *
 * Contexto: entidade central do pipeline de vendas. Negociações avançam
 * pelas etapas de um funil e podem ser visualizadas em modo Kanban ou lista.
 */
export interface Negotiation {
  id: string | number;
  title: string;
  /** Valor financeiro da negociação. */
  value?: number;
  /** Alias para valor da negociação. */
  amount?: number;
  status: NegotiationStatus;
  /** Posição da negociação dentro da etapa do funil (para ordenação Kanban). */
  position?: number;
  /** Data esperada de fechamento. */
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

/**
 * Payload para criação ou atualização de uma negociação.
 */
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

/**
 * Filtros para listagem de negociações.
 *
 * Contexto: parâmetros aceitos pelo endpoint de listagem de negociações em modo lista.
 */
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

/**
 * Filtros para visualização Kanban de negociações.
 *
 * Contexto: subconjunto dos filtros de listagem, sem paginação,
 * pois o Kanban usa cursor por etapa.
 */
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

/**
 * Etapa do funil com negociações no contexto do Kanban.
 *
 * Contexto: extensão de FunnelStep com dados de paginação cursor
 * para carregamento lazy das negociações por coluna.
 */
export interface NegotiationKanbanStep extends FunnelStep {
  negotiations?: Negotiation[];
  /** Total de negociações na etapa (independente da página carregada). */
  total_count?: number;
  /** Soma dos valores de todas as negociações da etapa. */
  total_value?: number;
  /** Indica se há mais negociações além das retornadas na página inicial. */
  has_more?: boolean;
  /** Cursor para buscar a próxima página desta etapa. */
  next_cursor?: string | null;
}

/**
 * Página de negociações de uma etapa do Kanban (paginação por cursor).
 *
 * Contexto: retornado ao carregar mais negociações em uma coluna do Kanban.
 */
export interface KanbanStepPage {
  negotiations: Negotiation[];
  has_more: boolean;
  next_cursor: string | null;
}

/**
 * Item de produto vinculado a uma negociação.
 *
 * Contexto: negociações podem ter múltiplos produtos/serviços associados
 * com quantidades e preços individuais.
 */
export interface NegotiationProductItem {
  id: string | number;
  negotiation_id: string | number;
  product_id: string | number;
  quantity: number;
  /** Preço unitário do produto na negociação. */
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

/**
 * Payload para criação ou atualização de produto em uma negociação.
 */
export interface NegotiationProductPayload {
  product_id?: string | number;
  crm_product_id?: string | number;
  name?: string;
  quantity?: number;
  price?: number;
  unit_price?: number;
  discount?: number;
}

/**
 * Resumo do usuário autor de uma anotação de negociação.
 */
export interface NegotiationAnnotationUser {
  id: string | number;
  name: string;
  avatar?: string | null;
}

/**
 * Representa uma anotação (nota de atividade) em uma negociação.
 *
 * Contexto: anotações registram o histórico de atividades da negociação
 * (chamadas, reuniões, notas manuais, mudanças de status, etc.).
 */
export interface NegotiationAnnotation {
  id: string | number;
  negotiation_id: string | number;
  user_id: string | number;
  content: string;
  /** Tipo da anotação (manual, sistema, chamada, reunião, etc.). */
  type: 'manual' | 'system' | 'status' | 'call' | 'email' | 'meeting' | string;
  /** Indica se a anotação está fixada no topo. */
  is_pinned: boolean;
  user?: NegotiationAnnotationUser | null;
  created_at?: string | null;
  updated_at?: string | null;
}

/**
 * Payload para criação ou atualização de uma anotação de negociação.
 */
export interface NegotiationAnnotationPayload {
  content: string;
  type?: string;
  is_pinned?: boolean;
}

/**
 * Representa o vínculo entre um contato e uma negociação.
 *
 * Contexto: uma negociação pode ter múltiplos contatos vinculados,
 * cada um com um papel específico (decisor, influenciador, etc.).
 */
export interface NegotiationContactLink {
  id: string | number;
  negotiation_id: string | number;
  contact_id: string | number;
  /** Papel do contato na negociação (ex: decisor, influenciador). */
  role?: string | null;
  /** Indica se este é o contato principal da negociação. */
  is_primary?: boolean;
  notes?: string | null;
  contact?: {
    id: string | number;
    name: string;
    email?: string | null;
    phone?: string | null;
    whatsapp?: string | null;
    crm_company_id?: string | number | null;
  } | null;
  created_at?: string | null;
}

/**
 * Payload para vinculação de contato a uma negociação.
 */
export interface NegotiationContactPayload {
  contact_id?: string | number;
  role?: string;
  is_primary?: boolean;
  notes?: string | null;
}

/**
 * Resumo do usuário que fez upload de um arquivo em uma negociação.
 */
export interface NegotiationFileUser {
  id: string | number;
  name: string;
}

/**
 * Representa um arquivo anexado a uma negociação.
 *
 * Contexto: negociações podem ter documentos anexados como propostas,
 * contratos, planilhas, etc.
 */
export interface NegotiationFile {
  id: string | number;
  negotiation_id: string | number;
  user_id?: string | number | null;
  name?: string | null;
  filename?: string | null;
  original_name?: string | null;
  path?: string | null;
  mime_type?: string | null;
  size?: number | null;
  /** Tamanho formatado para exibição (ex: "1.2 MB"). */
  formatted_size?: string | null;
  /** URL pré-assinada para download do arquivo. */
  url?: string | null;
  user?: NegotiationFileUser | null;
  created_at?: string | null;
}

/**
 * Resumo do usuário responsável por uma tarefa de negociação.
 */
export interface NegotiationTaskUser {
  id: string | number;
  name: string;
  avatar?: string | null;
}

/**
 * Representa uma tarefa vinculada a uma negociação do CRM.
 *
 * Contexto: tarefas organizam as ações necessárias para avançar
 * uma negociação. Podem gerar eventos na agenda e notificações.
 */
export interface NegotiationTask {
  id: string | number;
  negotiation_id: string | number;
  title: string;
  description?: string | null;
  /** Tipo de ação (call, email, meeting, etc.). */
  action_type?: string | null;
  /** Data de vencimento da tarefa. */
  due_date?: string | null;
  start_time?: string | null;
  end_time?: string | null;
  status?: string | null;
  reminder_at?: string | null;
  /** Indica se deve criar um evento na agenda ao salvar. */
  add_to_agenda?: boolean;
  /** ID do evento de agenda gerado, se aplicável. */
  agenda_event_id?: string | number | null;
  notify_ui?: boolean;
  notify_email?: boolean;
  notify_push?: boolean;
  notify_whatsapp?: boolean;
  is_completed: boolean;
  completed_at?: string | null;
  user_id?: string | number | null;
  assigned_to?: string | number | null;
  /** Prioridade da tarefa. */
  priority?: 'low' | 'medium' | 'high';
  user?: NegotiationTaskUser;
  negotiation?: {
    id: string | number;
    title: string;
    crm_company?: {
      id: string | number;
      name: string;
    } | null;
  } | null;
  created_at?: string;
  updated_at?: string;
}

/**
 * Payload para criação ou atualização de uma tarefa de negociação.
 */
export interface NegotiationTaskPayload {
  title: string;
  description?: string;
  action_type?: string;
  due_date?: string | null;
  start_time?: string | null;
  end_time?: string | null;
  status?: string;
  add_to_agenda?: boolean;
  notify_ui?: boolean;
  notify_email?: boolean;
  notify_push?: boolean;
  notify_whatsapp?: boolean;
}
