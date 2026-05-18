export interface SettingsUser {
  id: string;
  name: string;
  email: string;
  is_active: boolean;
  is_primary_tenant_user?: boolean;
  created_at?: string | null;
  role?: string | null;
  roles?: string[];
}

export type SettingsUserStatus = 'all' | 'active' | 'inactive';

export interface PaginatedSettingsUsers {
  data: SettingsUser[];
  meta: {
    current_page: number;
    total: number;
    per_page: number;
    last_page: number;
  };
}

export interface CreateSettingsUserPayload {
  name: string;
  email: string;
  password: string;
  password_confirmation?: string;
  roles?: string[];
  role?: string | null;
  is_active?: boolean;
  tenant_id?: string;
}

export interface UpdateSettingsUserPayload {
  name: string;
  email: string;
  password?: string | null;
  password_confirmation?: string;
  roles?: string[];
  role?: string | null;
  is_active?: boolean;
  tenant_id?: string;
}

export interface SettingsUsersFilters {
  search?: string;
  status?: SettingsUserStatus;
  page?: number;
  per_page?: number;
}
