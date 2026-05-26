/**
 * Métricas resumidas de alto nível para o dashboard.
 */
export interface DashboardSummary {
  /** Receita total de negociações ganhas. */
  total_revenue_won: number;
  /** Soma dos valores de negociações em aberto no pipeline. */
  pipeline_open_value: number;
  /** Quantidade de tickets de suporte atualmente abertos. */
  active_tickets_count: number;
  /** Nota média de CSAT (escala 0-5). */
  csat_average: number;
}

/**
 * Etapa individual do funil de vendas.
 */
export interface FunnelStep {
  /** Nome da etapa do funil. */
  step_name: string;
  /** Código hexadecimal de cor para a visualização da etapa. */
  step_color: string;
  /** Quantidade de negociações nesta etapa. */
  count: number;
  /** Valor total das negociações nesta etapa. */
  total_amount: number;
}

/**
 * Receita mensal detalhada por status.
 */
export interface RevenueMonth {
  /** Número do mês (1-12). */
  month: number;
  /** Ano. */
  year: number;
  /** Valor total de negociações ganhas. */
  won_amount: number;
  /** Valor total de negociações em aberto. */
  open_amount: number;
}

/**
 * Estatísticas de negociações por status e motivos de perda.
 */
export interface NegotiationStats {
  /** Contagem de negociações pelo status atual. */
  by_status: {
    open: number;
    won: number;
    lost: number;
  };
  /** Principais motivos de perda de negociações. */
  top_loss_reasons: { name: string; count: number }[];
}

/**
 * Métricas de volume e SLA de tickets de suporte.
 */
export interface TicketStats {
  /** Volume diário de tickets no período. */
  daily_volume: { date: string; count: number }[];
  /** Contagem de tickets agrupada por nível de prioridade. */
  by_priority: {
    low: number;
    normal: number;
    high: number;
    urgent: number;
  };
  /** Percentual de tickets que atingiram a meta de primeiro atendimento do SLA. */
  sla_compliance_rate: number;
  /** Média de minutos até o primeiro atendimento. */
  avg_first_response_minutes: number;
}

/**
 * Distribuição e resumo da nota de satisfação do cliente (CSAT).
 */
export interface CsatStats {
  /** Nota média de todas as avaliações (1-5). */
  average_rating: number;
  /** Total de avaliações recebidas. */
  total_evaluations: number;
  /** Distribuição de notas (1-5 estrelas). */
  distribution: Record<1 | 2 | 3 | 4 | 5, number>;
}

/**
 * Entrada de atividade recente para o feed de atividades.
 */
export interface RecentActivity {
  /** Tipo de atividade (ex.: 'negotiation_created', 'ticket_resolved'). */
  type: string;
  /** Título curto da atividade. */
  title: string;
  /** Descrição detalhada do que ocorreu. */
  description: string;
  /** Timestamp ISO 8601 de quando a atividade ocorreu. */
  created_at: string;
  /** Identificador do ícone para o tipo de atividade. */
  icon: string;
}

/**
 * Envelope completo de dados do dashboard retornado pela API.
 */
export interface DashboardData {
  /** Métricas resumidas principais. */
  summary: DashboardSummary;
  /** Detalhamento das etapas do funil de vendas. */
  funnel: FunnelStep[];
  /** Histórico mensal de receita. */
  revenue: RevenueMonth[];
  /** Estatísticas de negociações. */
  negotiations: NegotiationStats;
  /** Métricas de tickets de suporte. */
  tickets: TicketStats;
  /** Dados de satisfação do cliente. */
  csat: CsatStats;
  /** Entradas do feed de atividades recentes. */
  activities: RecentActivity[];
}

/**
 * Opções predefinidas de filtro de período.
 */
export type DashboardFilterOption =
  | 'today'
  | 'yesterday'
  | 'last7'
  | 'last15'
  | 'last30'
  | 'custom';

/**
 * Representa uma seleção de intervalo de datas personalizado.
 */
export interface DateRange {
  /** Data de início no formato ISO 8601 (AAAA-MM-DD). */
  from: string;
  /** Data de fim no formato ISO 8601 (AAAA-MM-DD). */
  to: string;
  /** Opção de filtro que definiu este intervalo. */
  option: DashboardFilterOption;
  /** Rótulo legível para o intervalo. */
  label: string;
}
