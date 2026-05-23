/**
 * Authenticated user data stored in the auth store.
 */
export interface AuthUser {
  id: string | number;
  name: string;
  email: string;
  avatar_url?: string | null;
  two_factor_enabled?: boolean;
  tenant_id?: string | number | null;
  company_id?: string | number | null;
  is_supervisor?: boolean;
  force_password_change?: boolean;
  tenant_plan?: {
    id: string;
    name: string;
    slug: string;
    ai_enabled: boolean;
    features?: {
      ai_agents_v2?: boolean;
      ai_prompts_governance?: boolean;
      ai_knowledge_base?: boolean;
      ai_usage_tracking?: boolean;
      chat_rewrite_v1?: boolean;
    };
  } | null;
  permissions: string[];
  is_impersonating?: boolean;
  impersonated_tenant?: {
    id: string;
    name: string;
  };
}

/**
 * Internal shape of the auth data persisted to localStorage.
 */
export interface StoredAuth {
  user: AuthUser | null;
  token: string | null;
}

/**
 * Resposta da API de autenticação contendo dados do usuario, plano e token.
 */
export interface AuthResponse {
  data: {
    user?: {
      id: string | number;
      name: string;
      email: string;
      avatar_url?: string | null;
      two_factor_enabled?: boolean;
      force_password_change?: boolean;
    };
    tenant_plan?: {
      id: string;
      name: string;
      slug: string;
      ai_enabled: boolean;
    } | null;
    token?: string;
    permissions?: string[];
    requires_2fa?: boolean;
    two_factor_required?: boolean;
    email?: string;
    is_impersonating?: boolean;
    impersonated_tenant?: {
      id: string;
      name: string;
    };
  };
}

/**
 * Resposta da API de menu de navegacao.
 */
export interface MenuResponse {
  data: {
    menu: {
      label: string;
      icon: string;
      route: string;
      permission?: string;
      children?: {
        label: string;
        route: string;
        permission?: string;
      }[];
    }[];
  };
}
