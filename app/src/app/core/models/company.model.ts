/**
 * Platform tenant company model.
 * Represents a tenant/company in the platform management context.
 */
export interface Company {
  id: string | number;
  name: string;
  segment_id?: string | null;
  segment_name?: string | null;
  document?: string;
  email?: string;
  phone?: string;
  street?: string;
  number?: string;
  complement?: string;
  district?: string;
  address?: string;
  city?: string;
  state?: string;
  zip?: string;
  zip_code?: string;
  zipcode?: string;
  plan_id?: string | null;
  is_active: boolean;
  tenant_code?: string;
  primary_email?: string;
  created_at?: string;
  deleted_at?: string;
}

/**
 * Filters for listing platform companies/tenants.
 */
export interface CompanyFilters {
  search?: string;
  is_active?: boolean;
  trashed?: boolean;
  created_from?: string;
  created_to?: string;
  per_page?: number;
  page?: number;
  sort_by?: 'name' | 'document' | 'is_active' | 'created_at';
  sort_dir?: 'asc' | 'desc';
  plan_id?: string | number;
}
