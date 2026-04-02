/**
 * Modelo de Role (Perfil de Acesso).
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
 * Permissões agrupadas por módulo.
 */
export type GroupedPermissions = Record<string, string[]>;

/**
 * Payload para criação/atualização de role.
 */
export interface RolePayload {
  name: string;
  permissions: string[];
}
