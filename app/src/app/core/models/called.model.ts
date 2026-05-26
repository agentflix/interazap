/**
 * Status possíveis de um ticket de atendimento (chamado).
 *
 * - `open`: aberto e aguardando atendimento
 * - `pending`: aguardando resposta do cliente
 * - `in_progress`: em atendimento ativo
 * - `closed`: encerrado
 */
export type CalledStatus = 'open' | 'pending' | 'in_progress' | 'closed';

/**
 * Canal de origem de um ticket de atendimento.
 *
 * - `whatsapp`: originado pelo WhatsApp
 * - `internal`: criado internamente pela equipe
 * - `external`: proveniente de integração externa
 * - `portal`: aberto pelo portal do cliente
 */
export type CalledChannel = 'whatsapp' | 'internal' | 'external' | 'portal';

/**
 * Sentimento detectado nas mensagens do ticket pela IA.
 */
export type CalledSentiment = 'positive' | 'neutral' | 'negative' | 'critical';

/**
 * Critério de ordenação para listagem de tickets.
 */
export type CalledSortBy = 'last_message_at' | 'sentiment_score';

/**
 * Resumo do usuário/agente responsável por um ticket.
 */
export interface CalledUserSummary {
  id: string | number;
  name: string;
  email?: string;
  department?: {
    id: string | number;
    name: string;
  } | null;
}

/**
 * Resumo do contato vinculado a um ticket.
 */
export interface CalledContactSummary {
  id: string | number;
  name: string;
  email?: string | null;
  phone?: string | null;
  whatsapp?: string | null;
  profile_picture_url?: string | null;
}

/**
 * Resumo da última mensagem de um ticket (exibida na lista de tickets).
 */
export interface CalledMessageSummary {
  id: string | number;
  content?: string | null;
  type?: string;
  created_at?: string | null;
}

/**
 * Resumo da avaliação do atendimento submetida pelo cliente.
 */
export interface CalledEvaluationSummary {
  has_evaluation: boolean;
  /** Nota de 1 a 5 dada pelo cliente. */
  rating?: number | null;
  comment?: string | null;
  submitted_at?: string | null;
}

/**
 * Representa um ticket de atendimento (chamado) completo.
 *
 * Contexto: entidade central do módulo de Chat. Cada ticket corresponde
 * a uma conversa entre um contato e a equipe de suporte/vendas.
 */
export interface Called {
  id: string | number;
  company_id: string | number;
  /** ID do agente/usuário responsável pelo atendimento. */
  user_id?: string | number | null;
  contact_id?: string | number | null;
  /** ID da instância de WhatsApp pela qual o ticket foi aberto. */
  instance_id?: string | number | null;
  /** Número de protocolo gerado automaticamente para o ticket. */
  protocol?: string | null;
  profile_picture_url?: string | null;
  status: CalledStatus;
  /** Indica se o bot/agente de IA está ativo neste ticket. */
  is_bot_active?: boolean | null;
  /** ID do agente de IA atualmente ativo no ticket. */
  current_ai_agent_id?: string | null;
  channel: CalledChannel;
  subject?: string | null;
  notes?: string | null;
  queued_at?: string | null;
  started_at?: string | null;
  closed_at?: string | null;
  closed_by?: string | null;
  close_reason?: string | null;
  /** Modo de encerramento: normal (cliente/agente) ou forçado (sistema). */
  closed_mode?: 'normal' | 'forced' | null;
  /** Duração da espera em fila em segundos. */
  wait_duration_seconds?: number | null;
  /** Duração total do atendimento em segundos. */
  service_duration_seconds?: number | null;
  created_at?: string | null;
  updated_at?: string | null;
  user?: CalledUserSummary | null;
  assigned_user?: CalledUserSummary | null;
  contact?: CalledContactSummary | null;
  last_message?: CalledMessageSummary | null;
  evaluation?: CalledEvaluationSummary | null;
  /** Quantidade de mensagens não lidas pelo agente. */
  unread_count?: number;
  sentiment?: CalledSentiment | null;
  /** Pontuação numérica do sentimento (0 a 1). */
  sentiment_score?: number | null;
  sentiment_updated_at?: string | null;
}

/**
 * Filtros para listagem de tickets de atendimento.
 *
 * Contexto: parâmetros aceitos pelo endpoint de listagem de tickets.
 */
export interface CalledListFilters {
  search?: string;
  status?: CalledStatus;
  channel?: CalledChannel;
  contact_id?: string | number;
  instance_id?: string | number;
  user_id?: string | number;
  agent_id?: string | number;
  from?: string;
  to?: string;
  per_page?: number;
  page?: number;
  sentiment?: CalledSentiment;
  sort_by?: CalledSortBy;
  /** Agrupa múltiplos tickets do mesmo contato. */
  group_by_contact?: boolean;
}

/**
 * Resposta paginada da API para listagem de tickets.
 */
export interface CalledListResponse {
  data: Called[];
  meta: {
    current_page: number;
    total: number;
    per_page: number;
    last_page: number;
  };
  /** Contagem de tickets por status. */
  counts?: CalledCounts;
}

/**
 * Contagem de tickets agrupados por status.
 *
 * Contexto: exibido nas abas da lista de tickets para navegação rápida.
 */
export interface CalledCounts {
  all: number;
  pending: number;
  open: number;
  closed: number;
  in_progress?: number;
}

/**
 * Payload para criação de um novo ticket de atendimento.
 */
export interface CalledCreatePayload {
  contact_id: string | number;
  instance_id?: string | number;
  channel?: CalledChannel;
  subject?: string;
  notes?: string;
}

/**
 * Opções para busca de um ticket individual.
 */
export interface CalledGetOptions {
  includeMessages?: boolean;
  messagesPerPage?: number;
}

/**
 * Resposta da API para um único ticket de atendimento.
 */
export interface CalledResponse {
  data: {
    called: Called;
  };
}

/**
 * Representa a transferência de um ticket entre agentes ou departamentos.
 */
export interface ChatTicketTransfer {
  id: string | number;
  ticket_id: string | number;
  from_user_id: string | number | null;
  to_user_id: string | number;
  reason: string;
  status: string;
  transferred_at?: string | null;
}

/**
 * Payload para encerramento de um ticket de atendimento.
 */
export interface CalledClosePayload {
  /** Modo de encerramento: normal ou forçado. */
  mode?: 'normal' | 'forced';
  reason?: string;
}

/**
 * Representa uma mensagem resumida dentro do contexto de um ticket.
 *
 * Contexto: usado em listagens de mensagens simplificadas de um ticket.
 */
export interface CalledMessage {
  id: string | number;
  ticket_id: string | number;
  content?: string | null;
  type?: string;
  direction: 'incoming' | 'outgoing';
  status?: string;
  created_at?: string | null;
  user?: CalledUserSummary | null;
}

/**
 * Resposta paginada da API para mensagens de um ticket.
 */
export interface CalledMessagesResponse {
  data: CalledMessage[];
  meta: {
    current_page: number;
    total: number;
    per_page: number;
    last_page: number;
  };
}
