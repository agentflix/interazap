/**
 * Dados do perfil do usuário autenticado.
 *
 * Contexto: retornado pelo endpoint de perfil próprio (/profile/me)
 * e usado na página de configurações de conta do usuário.
 */
export interface ProfileUser {
  id: string | number;
  name: string;
  email: string;
  avatar_url?: string | null;
  /** Indica se a autenticação de dois fatores está habilitada para este usuário. */
  two_factor_enabled?: boolean;
}

/**
 * Resposta da API para busca do perfil do usuário.
 */
export interface ProfileResponse {
  data: ProfileUser;
}

/**
 * Payload para atualização dos dados do perfil do usuário.
 */
export interface UpdateProfilePayload {
  name: string;
}

/**
 * Payload para alteração de senha do usuário.
 */
export interface UpdatePasswordPayload {
  /** Senha atual para confirmação de identidade. */
  current_password: string;
  /** Nova senha desejada. */
  password: string;
  /** Confirmação da nova senha (deve ser idêntica a `password`). */
  password_confirmation: string;
}

/**
 * Resposta da API após upload de imagem de perfil.
 */
export interface ProfileImageResponse {
  data: {
    /** URL da nova imagem de avatar após o upload. */
    avatar_url?: string | null;
  };
}
