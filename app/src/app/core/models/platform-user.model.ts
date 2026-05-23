export interface PlatformUser {
  id: string;
  name: string;
  email: string;
  roles?: string[];
  avatar_url?: string;
  tenant_id?: string;
  department_id?: string | number;
  company_id?: string | number;
  is_active: boolean;
  force_password_change?: boolean;
  is_primary_tenant_user?: boolean;
  tenant?: {
    id: string;
    name: string;
    document?: string;
  };
  company?: {
    id: string | number;
    name: string;
    document?: string;
  };
}

export interface PlatformUserListResponse {
  data: PlatformUser[];
  meta?: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
}

export interface PlatformUserFilters {
  search?: string | undefined;
  is_active?: boolean;
  page?: number;
  per_page?: number;
  sort_by?: string;
  sort_dir?: string;
  tenant_id?: string;
}
