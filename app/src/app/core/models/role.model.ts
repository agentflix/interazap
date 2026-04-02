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
