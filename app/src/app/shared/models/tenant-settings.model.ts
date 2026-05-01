/**
 * Tenant settings model.
 * Mirrors the backend `settings_localization`, `settings_privacy`, and `settings_chat`
 * JSONB columns stored in `platform_tenants`.
 */

/**
 * Localization settings — timezone, date, time, and currency formats.
 */
export interface TenantLocalizationSettings {
  timezone: string;
  dateFormat: 'DD/MM/YYYY' | 'MM/DD/YYYY' | 'YYYY-MM-DD';
  timeFormat: '12h' | '24h';
  currencyFormat: 'BRL' | 'USD' | 'EUR';
}

/**
 * Privacy settings — presence visibility, read receipts, and notification previews.
 */
export interface TenantPrivacySettings {
  presence: 'all' | 'team' | 'hidden';
  readReceipt: boolean;
  notificationPreview: boolean;
}

/**
 * Chat auto-close settings — automatically close tickets after inactivity.
 */
export interface TenantChatAutoCloseSettings {
  auto_close_inactivity_enabled: boolean;
  auto_close_inactivity_minutes: number;
  auto_close_inactivity_target: 'both' | 'client' | 'agent';
  auto_close_inactivity_message: string;
}

/**
 * Complete tenant settings structure persisted in the backend.
 */
export interface TenantSettings {
  settings_localization: TenantLocalizationSettings;
  settings_privacy: TenantPrivacySettings;
  settings_chat?: TenantChatAutoCloseSettings;
}

/**
 * API response envelope for tenant settings.
 */
export interface TenantSettingsResponse {
  data: TenantSettings;
}
