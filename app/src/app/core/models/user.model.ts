import { type Company } from '@core/models/company.model';

/**
 * Represents a user in the system.
 */
export interface User {
  id: string;
  name: string;
  email: string;
  role?: string;
  avatar_url?: string;
  tenant_id?: string;
  department_id?: string | number;
  company_id?: string | number;
  company?: Company;
  is_active: boolean;
  is_primary_tenant_user?: boolean;
}

/**
 * Payload for creating or updating a user.
 */
export interface UserUpsertPayload extends Partial<User> {
  password?: string;
  password_confirmation?: string;
}

/**
 * Response structure for paginated user list.
 */
export interface UserListResponse {
  data: User[];
  meta?: {
    total: number;
    per_page: number;
    current_page: number;
    last_page: number;
  };
}

/**
 * Filters for listing users.
 */
export interface UserFilters {
  search?: string | undefined;
  is_active?: boolean;
  page?: number;
  per_page?: number;
  sort_by?: string;
  sort_dir?: string;
}
