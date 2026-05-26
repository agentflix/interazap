/**
 * Plano de assinatura exibido para o tenant.
 */
export interface SubscriptionPlan {
  id: string;
  name: string;
  slug: string;
  price_monthly: string;
  limit_users: number;
  whatsapp_integrations_limit: number;
  storage_mode: 'LIMITED' | 'UNLIMITED';
  storage_limit_bytes: number | null;
  storage_formatted?: string;
  ai_enabled: boolean;
  negotiations_mode: 'LIMITED' | 'UNLIMITED';
  negotiations_limit: number | null;
  message_limit_monthly: number | null;
  overage_mode: 'stop' | 'overage';
  overage_price_per_message: number | null;
  cycle_days: number;
  is_trial: boolean;
  is_current?: boolean;
  features?: SubscriptionPlanFeature[];
}

/**
 * Feature visual de um plano no card de comparação.
 */
export interface SubscriptionPlanFeature {
  label: string;
  included: boolean;
}

/**
 * Resumo de uso de um recurso limitado.
 */
export interface SubscriptionUsageItem {
  current: number;
  limit: number | null;
  percentage: number | null;
}

/**
 * Resumo de uso de storage.
 */
export interface SubscriptionStorageUsage {
  used_bytes: number;
  limit_bytes: number | null;
  used_formatted: string;
  limit_formatted: string;
  percentage: number | null;
  mode: 'LIMITED' | 'UNLIMITED';
  is_full: boolean;
}

/**
 * Uso de mensagens IA no ciclo de cobrança atual.
 */
export interface SubscriptionAiMessagesUsage {
  current: number;
  limit: number | null;
  percentage: number;
  overage_count: number;
  mode: 'stop' | 'overage';
  overage_price: number | null;
  cycle_start: string;
  cycle_end: string;
  cycle_label: string;
}

/**
 * Estrutura de uso retornada pela API de assinatura.
 */
export interface SubscriptionUsage {
  users: SubscriptionUsageItem;
  instances: SubscriptionUsageItem;
  storage: SubscriptionStorageUsage;
  negotiations: SubscriptionUsageItem & { mode: 'LIMITED' | 'UNLIMITED' };
  ai: { enabled: boolean };
  ai_messages: SubscriptionAiMessagesUsage;
}

/**
 * Próxima fatura prevista para o tenant.
 */
export interface SubscriptionNextInvoice {
  due_date: string | null;
  estimated_amount: string;
  credit_available: string;
}

/**
 * Payload principal da tela Meu Plano.
 */
export interface SubscriptionSummary {
  current_plan: SubscriptionPlan | null;
  usage: SubscriptionUsage;
  next_invoice: SubscriptionNextInvoice;
}

/**
 * Preview de impacto financeiro e operacional da troca de plano.
 */
export interface PlanChangePreview {
  type: 'upgrade' | 'downgrade';
  new_plan: { id: string; name: string; price_monthly: string };
  financial: {
    pro_rata_charge: string | null;
    pro_rata_credit: string | null;
    days_remaining: number;
    days_in_month: number;
    formula: string | null;
  };
  affected_resources: {
    users: {
      will_deactivate: number;
      current: number;
      new_limit: number;
      affected: { id: string; name: string; last_login_at: string }[];
    };
    instances: {
      will_deactivate: number;
      current: number;
      new_limit: number;
      affected: { id: string; name: string; created_at: string | null }[];
    };
    storage: {
      is_over_limit: boolean;
      current_bytes: number;
      new_limit_bytes: number | null;
      impact: string;
    };
    negotiations: {
      impact: 'none';
      message: string;
    };
  };
}

/**
 * Resultado da execução da troca de plano.
 */
export interface PlanChangeResult {
  type: 'upgrade' | 'downgrade';
  new_plan: { id: string; name: string; price_monthly: string };
  pro_rata_invoice?: {
    id: string;
    amount: string;
    due_date: string;
    status: string;
  } | null;
  pro_rata_credit?: string;
  affected_resources: {
    users_deactivated?: number;
    instances_deactivated?: number;
    storage_blocked?: boolean;
    negotiations_affected?: boolean;
  };
  message: string;
}
