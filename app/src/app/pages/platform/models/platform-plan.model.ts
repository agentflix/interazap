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
  storage_mode: LimitMode;
  storage_limit_bytes?: number | null;
  storage_limit_gb?: number | null;
  ai_enabled: boolean;
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
  storage_mode: LimitMode;
  storage_limit_bytes?: number | null;
  ai_enabled: boolean;
  whatsapp_integrations_limit: number;
  negotiations_mode: LimitMode;
  negotiations_limit?: number | null;
  price_monthly: number;
  is_active?: boolean;
}
