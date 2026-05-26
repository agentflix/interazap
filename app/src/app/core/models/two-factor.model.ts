/**
 * Resposta da API com o status atual da autenticação de dois fatores do usuário.
 */
export interface TwoFactorStatusResponse {
  data: {
    /** Indica se o 2FA está habilitado para o usuário. */
    enabled: boolean;
    /** Indica se o usuário possui códigos de recuperação disponíveis. */
    has_recovery_codes?: boolean;
  };
}

/**
 * Resposta da API com os dados de configuração inicial do 2FA.
 *
 * Contexto: retornado ao iniciar o processo de habilitação do 2FA.
 * O usuário deve escanear o QR Code com um autenticador TOTP.
 */
export interface TwoFactorSetupResponse {
  data: {
    /** Chave secreta TOTP para configuração manual. */
    secret: string;
    /** URL do QR Code para escanear com o autenticador (formato otpauth://). */
    qr_code_url: string;
  };
}

/**
 * Resposta da API após validação e habilitação do 2FA.
 *
 * Contém os códigos de recuperação que o usuário deve guardar com segurança.
 */
export interface TwoFactorValidateResponse {
  data: {
    /** Lista de códigos de recuperação de uso único. */
    recovery_codes: string[];
  };
}

/**
 * Resposta da API após regeneração dos códigos de recuperação do 2FA.
 *
 * Contexto: os códigos antigos são invalidados e os novos devem ser guardados.
 */
export interface TwoFactorRegenerateResponse {
  data: {
    /** Nova lista de códigos de recuperação gerados. */
    recovery_codes: string[];
  };
}
