/**
 * Modelo de configurações do tenant.
 * Espelha as colunas JSONB `settings_localization`, `settings_privacy` e `settings_chat`
 * armazenadas em `platform_tenants`.
 */

/**
 * Configurações de localização — fuso horário, formatos de data, hora e moeda.
 */
export interface TenantLocalizationSettings {
  timezone: string;
  dateFormat: 'DD/MM/YYYY' | 'MM/DD/YYYY' | 'YYYY-MM-DD';
  timeFormat: '12h' | '24h';
  currencyFormat: 'BRL' | 'USD' | 'EUR';
}

/**
 * Configurações de privacidade — visibilidade de presença, confirmação de leitura e previews.
 */
export interface TenantPrivacySettings {
  presence: 'all' | 'team' | 'hidden';
  readReceipt: boolean;
  notificationPreview: boolean;
}

/**
 * Configurações de auto-fechamento de chat — fecha tickets automaticamente após inatividade.
 */
export interface TenantChatAutoCloseSettings {
  auto_close_inactivity_enabled: boolean;
  auto_close_inactivity_minutes: number;
  auto_close_inactivity_target: 'both' | 'client' | 'agent';
  auto_close_inactivity_message: string;
}

/**
 * Estrutura completa de configurações do tenant persistida no backend.
 */
export interface TenantSettings {
  settings_localization: TenantLocalizationSettings;
  settings_privacy: TenantPrivacySettings;
  settings_chat?: TenantChatAutoCloseSettings;
}

/**
 * Envelope de resposta da API para configurações do tenant.
 */
export interface TenantSettingsResponse {
  data: TenantSettings;
}
