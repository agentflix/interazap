/**
 * Platform Plan Models
 *
 * @description Interfaces e tipos para planos da plataforma.
 */

/**
 * Modo de limite de armazenamento/negociações.
 */
export type LimitMode = 'UNLIMITED' | 'LIMITED';

/**
 * Plano de assinatura da plataforma.
 */
export interface PlatformPlan {
  id: string;
  name: string;
  slug: string;
  limit_users: number;
  chat_channels_limit: number;
  storage_mode: LimitMode;
  storage_limit_bytes?: number | null;
  storage_limit_gb?: number | null;
  ai_enabled: boolean;
  message_limit_monthly: number;
  overage_mode: 'stop' | 'overage';
  overage_price_per_message: string | null;
  whatsapp_integrations_limit: number;
  negotiations_mode: LimitMode;
  negotiations_limit?: number | null;
  price_monthly: string;
  asaas_product_id?: string | null;
  is_active: boolean;
  created_at?: string;
  updated_at?: string;
}

/**
 * Filtros para listagem de planos.
 */
export interface PlatformPlanFilters {
  search?: string;
  page?: number;
  per_page?: number;
}

/**
 * Payload para criação/atualização de plano.
 */
export interface PlatformPlanPayload {
  name: string;
  slug?: string | null;
  limit_users: number;
  chat_channels_limit: number;
  storage_mode: LimitMode;
  storage_limit_bytes?: number | null;
  ai_enabled: boolean;
  message_limit_monthly: number;
  overage_mode: 'stop' | 'overage';
  overage_price_per_message?: number | null;
  whatsapp_integrations_limit: number;
  negotiations_mode: LimitMode;
  negotiations_limit?: number | null;
  price_monthly: number;
  is_active?: boolean;
}

export interface Paginated<T> {
  data: T[];
  meta: {
    current_page: number;
    total: number;
    per_page: number;
    last_page: number;
  };
}
