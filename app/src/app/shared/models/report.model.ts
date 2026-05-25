/**
 * Modelos para o módulo de relatórios.
 */

/** Filtros comuns para todos os relatórios. */
export interface ReportFilter {
  start_date: string;
  end_date: string;
  granularity?: 'day' | 'week' | 'month' | undefined;
  user_id?: string;
  funnel_id?: string;
  step_id?: string;
  channel?: 'whatsapp' | 'telegram' | 'webchat' | undefined;
  instance_id?: string;
  reason_loss_id?: string;
  product_id?: string;
}

/** Meta retornada pela API em cada relatório. */
export interface ReportMeta {
  start_date: string;
  end_date: string;
  total: number;
  generated_at: string;
}

/** Envelope padrão de resposta de relatório — espelha o formato real da API. */
export interface ReportResponse<T> {
  success: boolean;
  message: string;
  data: {
    data: T;
    meta: ReportMeta;
  };
}

// ─── Sales Funnel ───

export interface FunnelStep {
  step_name: string;
  step_color: string;
  step_order: number;
  count: number;
  total_amount: number;
  avg_days_in_step: number;
  conversion_rate_to_next: number;
  overdue_count: number;
}

export interface SalesFunnelReport {
  steps: FunnelStep[];
  total_negotiations: number;
  total_pipeline_value: number;
}

// ─── Revenue & Sales ───

export interface RevenueTimeSeriesItem {
  date: string;
  revenue: number;
  won_count: number;
}

export interface RevenueRankingItem {
  user_name: string;
  revenue: number;
  won_count: number;
  win_rate: number;
}

export interface LossRevenueItem {
  reason_name: string;
  lost_count: number;
  lost_amount: number;
}

export interface RevenueSalesReport {
  summary: {
    total_revenue: number;
    avg_ticket: number;
    won_count: number;
    lost_count: number;
    open_count: number;
    win_rate: number;
  };
  time_series: RevenueTimeSeriesItem[];
  ranking: RevenueRankingItem[];
  loss_reasons: LossRevenueItem[];
}

// ─── Salesperson Performance ───

export interface SalespersonItem {
  user_id: string;
  user_name: string;
  won_count: number;
  lost_count: number;
  open_count: number;
  win_rate: number;
  total_revenue: number;
  avg_ticket: number;
  avg_close_days: number;
  tasks_done: number;
  tasks_overdue: number;
  proposals_sent: number;
  proposals_accepted: number;
}

export interface SalespersonReport {
  salespersons: SalespersonItem[];
}

// ─── Loss Reason ───

export interface LossReasonItem {
  reason_name: string;
  count: number;
  total_amount: number;
  percentage: number;
}

export interface LossReasonByStep {
  step_name: string;
  reason_name: string;
  count: number;
}

export interface LossReasonReport {
  reasons: LossReasonItem[];
  by_step: LossReasonByStep[];
  by_user: { user_name: string; reason_name: string; count: number }[];
  time_series: { date: string; reason_name: string; count: number }[];
}

// ─── SLA & Resolution ───

export interface SlaReport {
  summary: {
    sla_first_response_rate: number;
    sla_resolution_rate: number;
    avg_first_response_minutes: number;
    avg_resolution_hours: number;
  };
  by_priority: {
    priority: string;
    count: number;
    avg_first_response_minutes: number;
    avg_resolution_hours: number;
  }[];
  by_agent: {
    agent_name: string;
    sla_compliance: number;
    avg_first_response_minutes: number;
    avg_resolution_hours: number;
  }[];
  overdue_tickets: {
    over_24h: number;
    over_48h: number;
    over_72h: number;
  };
}

// ─── Agent Performance ───

export interface AgentPerformanceItem {
  agent_id: string;
  agent_name: string;
  tickets_handled: number;
  tickets_resolved: number;
  avg_first_response_min: number;
  avg_resolution_hours: number;
  csat_avg: number;
  sla_violations: number;
  messages_sent: number;
  open_now: number;
}

export interface AgentPerformanceReport {
  agents: AgentPerformanceItem[];
}

// ─── CSAT / NPS ───

export interface CsatNpsReport {
  nps_score: number;
  csat_average: number;
  total_evaluations: number;
  response_rate: number;
  distribution: Record<string, number>;
  time_series: { date: string; csat_avg: number; count: number }[];
  by_agent: { agent_name: string; csat_avg: number; count: number }[];
  by_channel: { channel: string; csat_avg: number; count: number }[];
  negative_comments: {
    rating: number;
    comment: string;
    created_at: string;
    agent_name: string;
  }[];
}

// ─── Chat Volume ───

export interface ChatVolumeReport {
  summary: {
    total_tickets: number;
    avg_daily: number;
    auto_resolution_rate: number;
    human_takeover_count: number;
  };
  by_channel: { channel: string; count: number }[];
  by_instance: { instance_name: string; count: number }[];
  heatmap: number[][];
  by_sentiment: {
    positive: number;
    neutral: number;
    negative: number;
  };
}

// ─── AI Usage & Cost ───

export interface AiFeatureUsage {
  feature: string;
  total_tokens: number;
  total_cost: number;
  avg_latency_ms: number;
  call_count: number;
}

export interface AiUsageReport {
  summary: {
    total_tokens: number;
    total_cost: number;
    avg_latency_ms: number;
    total_calls: number;
  };
  by_feature: AiFeatureUsage[];
  by_model: { model_name: string; provider: string; cost: number; calls: number }[];
  top_users: { user_name: string; tokens: number; cost: number }[];
  daily_cost: { date: string; cost: number }[];
  playbooks: { name: string; runs: number; success_rate: number }[];
}

// ─── Billing ───

export interface BillingReport {
  summary: {
    mrr: number;
    total_billed: number;
    total_paid: number;
    total_pending: number;
    total_overdue: number;
    delinquency_rate: number;
    avg_days_to_pay: number;
  };
  by_plan: { plan_name: string; mrr: number; count: number }[];
  by_payment_method: { method: string; count: number; amount: number }[];
  monthly_revenue: { month: string; revenue: number }[];
  upcoming_due: {
    invoice_id: string;
    tenant_name: string;
    amount: number;
    due_date: string;
  }[];
}

// ─── Product Performance ───

export interface ProductPerformanceItem {
  product_id: string;
  product_name: string;
  type: string;
  sold_qty: number;
  total_revenue: number;
  avg_unit_price: number;
  pipeline_qty: number;
  pipeline_value: number;
  proposals_sent: number;
  proposals_accepted: number;
  acceptance_rate: number;
}

export interface ProductReport {
  products: ProductPerformanceItem[];
}

// ─── Autopilot Performance ───

export interface AutopilotPlaybookItem {
  playbook_id: string;
  name: string;
  total_runs: number;
  success_rate: number;
  avg_duration_ms: number;
  guardrail_blocked_rate: number;
}

export interface AutopilotReport {
  playbooks: AutopilotPlaybookItem[];
  by_trigger: { trigger_type: string; count: number }[];
  pending_approvals: number;
  daily_runs: { date: string; count: number }[];
}

// ─── Team Activity ───

export interface TeamMemberActivity {
  user_id: string;
  user_name: string;
  last_login_at: string | null;
  tickets_created: number;
  tickets_resolved: number;
  negotiations_created: number;
  negotiations_won: number;
  tasks_done: number;
  notes_created: number;
  events_created: number;
  is_inactive: boolean;
}

export interface TeamActivityReport {
  members: TeamMemberActivity[];
  inactive_count: number;
}

// ─── Contact CRM ───

export interface ContactCrmReport {
  summary: {
    total_active: number;
    total_inactive: number;
    new_in_period: number;
    cold_leads: number;
  };
  by_company: { company_name: string; count: number }[];
  top_tags: { tag_name: string; count: number }[];
  no_interaction: {
    days_30: number;
    days_60: number;
    days_90: number;
  };
  monthly_growth: { month: string; count: number }[];
}

// ─── Report Type Map ───

/** Dados de relatório de transcrição de mídia. */
export interface MediaTranscriptionReport {
  summary: {
    total_transcriptions: number;
    total_tokens: number;
    total_cost: number;
    avg_latency_ms: number;
  };
  by_type: { media_type: string; count: number; tokens: number; cost: number }[];
  by_model: { model_name: string; count: number; tokens: number; cost: number }[];
  daily_cost: { date: string; count: number; cost: number }[];
}

/** Mapa de tipo de relatório para seu tipo de dados. */
export interface ReportTypeMap {
  'sales-funnel': SalesFunnelReport;
  'revenue-sales': RevenueSalesReport;
  'salesperson-performance': SalespersonReport;
  'loss-reasons': LossReasonReport;
  'sla-resolution': SlaReport;
  'agent-performance': AgentPerformanceReport;
  'csat-nps': CsatNpsReport;
  'chat-volume': ChatVolumeReport;
  'ai-usage-cost': AiUsageReport;
  'media-transcription': MediaTranscriptionReport;
  billing: BillingReport;
  'product-performance': ProductReport;
  'autopilot-performance': AutopilotReport;
  'team-activity': TeamActivityReport;
  'contact-crm': ContactCrmReport;
}

/** Tipos de relatório disponíveis. */
export type ReportType = keyof ReportTypeMap;
