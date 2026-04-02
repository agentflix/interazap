/**
 * Model for tenant details panel data (company + plan + resources).
 */
export interface TenantDetails {
  company: TenantCompanyInfo;
  contracted_plan: TenantPlanInfo | null;
  resources: TenantResourcesInfo;
}

/** Company/organization data for the tenant. */
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

/** Contracted plan info for the tenant. */
export interface TenantPlanInfo {
  id: string;
  name: string;
  slug: string;
  price_monthly: string;
  is_active: boolean;
}

/** Resource usage and limits for the tenant. */
export interface TenantResourcesInfo {
  users: ResourceUsage;
  instances: ResourceUsage;
  storage: StorageUsage;
  ai: AiUsage;
  negotiations: NegotiationUsage;
}

/** Generic resource usage with current/limit/available. */
export interface ResourceUsage {
  current: number;
  limit: number | null;
  available: number | null;
}

/** Storage-specific usage data. */
export interface StorageUsage {
  used_bytes: number;
  limit_bytes: number | null;
  available_bytes: number | null;
  used_gb: number;
  limit_gb: number | null;
  available_gb: number | null;
  mode: 'LIMITED' | 'UNLIMITED';
}

/** AI feature availability. */
export interface AiUsage {
  enabled: boolean;
}

/** Negotiation resource usage. */
export interface NegotiationUsage {
  current: number;
  limit: number | null;
  available: number | null;
  mode: 'LIMITED' | 'UNLIMITED';
}
