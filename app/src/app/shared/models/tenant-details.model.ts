/**
 * Modelo de detalhes do tenant (empresa + plano + recursos).
 */
export interface TenantDetails {
  company: TenantCompanyInfo;
  contracted_plan: TenantPlanInfo | null;
  resources: TenantResourcesInfo;
}

/** Dados da empresa/organização do tenant. */
export interface TenantCompanyInfo {
  id: string;
  name: string;
  tenant_code: string | null;
  document: string | null;
  primary_email: string | null;
  phone: string | null;
  address: string | null;
  street: string | null;
  number: string | null;
  complement: string | null;
  district: string | null;
  city: string | null;
  state: string | null;
  zip_code: string | null;
  is_active: boolean;
  created_at: string | null;
}

/** Informações do plano contratado do tenant. */
export interface TenantPlanInfo {
  id: string;
  name: string;
  slug: string;
  price_monthly: string;
  is_active: boolean;
}

/** Uso e limites de recursos do tenant. */
export interface TenantResourcesInfo {
  users: ResourceUsage;
  instances: ResourceUsage;
  storage: StorageUsage;
  ai: AiUsage;
  negotiations: NegotiationUsage;
}

/** Uso genérico de recurso com atual/limite/disponível. */
export interface ResourceUsage {
  current: number;
  limit: number | null;
  available: number | null;
}

/** Dados de uso específicos de storage. */
export interface StorageUsage {
  used_bytes: number;
  limit_bytes: number | null;
  available_bytes: number | null;
  used_gb: number;
  limit_gb: number | null;
  available_gb: number | null;
  mode: 'LIMITED' | 'UNLIMITED';
}

/** Disponibilidade de funcionalidades de IA. */
export interface AiUsage {
  enabled: boolean;
}

/** Uso de recurso de negociações. */
export interface NegotiationUsage {
  current: number;
  limit: number | null;
  available: number | null;
  mode: 'LIMITED' | 'UNLIMITED';
}
