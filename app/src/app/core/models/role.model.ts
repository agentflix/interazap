/**
 * Represents an access control role (perfil de acesso) in the system.
 * Contains role metadata along with associated permissions and user counts.
 *
 * @example
 * ```typescript
 * const role: Role = {
 *   id: 'role-123',
 *   name: 'Admin',
 *   permissions: ['users.view', 'users.edit', 'reports.view'],
 *   permissions_count: 3,
 *   users_count: 5
 * };
 * ```
 */
export interface Role {
  id: string;
  name: string;
  permissions: string[];
  permissions_count: number;
  users_count: number;
  created_at: string;
  updated_at: string;
}

/**
 * Permissions organized by module/group name.
 * Keys represent module names and values are arrays of permission strings.
 *
 * @example
 * ```typescript
 * const grouped: GroupedPermissions = {
 *   'users': ['users.view', 'users.edit', 'users.delete'],
 *   'reports': ['reports.view', 'reports.export']
 * };
 * ```
 */
export type GroupedPermissions = Record<string, string[]>;

/**
 * Payload data required for creating or updating a role.
 *
 * @example
 * ```typescript
 * const payload: RolePayload = {
 *   name: 'Editor',
 *   permissions: ['posts.view', 'posts.edit']
 * };
 * ```
 */
export interface RolePayload {
  name: string;
  permissions: string[];
}

/**
 * Represents a user listed within a role context.
 * Contains user metadata and their assigned roles.
 */
export interface RoleUser {
  id: string;
  name: string;
  email: string;
  phone?: string;
  avatar_url?: string;
  is_active: boolean;
  roles: string[];
  created_at: string;
  updated_at: string;
}

/**
 * Paginated response for role users listing.
 */
export interface PaginatedRoleUsers {
  data: RoleUser[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

/**
 * Filters for listing roles.
 */
export interface RoleFilters {
  search?: string;
  page?: number;
  per_page?: number;
}

/**
 * Filters for listing users by role.
 */
export interface RoleUsersFilters {
  search?: string;
  page?: number;
  per_page?: number;
}

/**
 * Paginated response for roles listing.
 */
export interface PaginatedRoles {
  data: Role[];
  meta: {
    current_page: number;
    total: number;
    per_page: number;
    last_page: number;
  };
}

/**
 * Generic API response wrapper.
 */
export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}
