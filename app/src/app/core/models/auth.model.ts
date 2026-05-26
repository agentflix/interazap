/**
 * Dados do usuário autenticado armazenados no auth store.
 *
 * Contexto: populado após login bem-sucedido e persistido em memória via AuthStoreService.
 */
export interface AuthUser {
  id: string | number;
  name: string;
  email: string;
  /** URL do avatar do usuário, pode ser nula. */
  avatar_url?: string | null;
  /** Indica se a autenticação de dois fatores está habilitada. */
  two_factor_enabled?: boolean;
  /** ID do tenant ao qual o usuário pertence. */
  tenant_id?: string | number | null;
  /** ID da empresa do usuário. */
  company_id?: string | number | null;
  /** Indica se o usuário tem perfil de supervisor. */
  is_supervisor?: boolean;
  /** Indica se o usuário deve ser forçado a trocar a senha no próximo acesso. */
  force_password_change?: boolean;
  /** Plano do tenant associado ao usuário, com flags de funcionalidades de IA. */
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
  /** Lista de permissões concedidas ao usuário. */
  permissions: string[];
  /** Indica se a sessão atual é de impersonação de tenant. */
  is_impersonating?: boolean;
  /** Dados do tenant impersonado quando em modo de impersonação. */
  impersonated_tenant?: {
    id: string;
    name: string;
  };
}

/**
 * Estrutura interna dos dados de autenticação persistidos no localStorage.
 *
 * Contexto: usado por AuthStoreService para restaurar a sessão após recarga da página.
 */
export interface StoredAuth {
  user: AuthUser | null;
  token: string | null;
}

/**
 * Resposta da API de autenticação contendo dados do usuário, plano e token.
 *
 * Contexto: retornada pelos endpoints de login, OAuth Google e verificação 2FA.
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
 * Resposta da API de menu de navegação.
 *
 * Contexto: retornada pelo endpoint que fornece a estrutura dinâmica do menu lateral.
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
