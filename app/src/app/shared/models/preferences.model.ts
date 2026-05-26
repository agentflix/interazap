/**
 * Modelo de preferências do usuário.
 * Espelha o JSONB `preferences` armazenado em `auth_users`.
 */

/**
 * Preferências de aparência — tema, densidade e tamanho da fonte.
 */
export interface AppearancePreferences {
  theme: 'light' | 'dark' | 'system';
  density: 'compact' | 'normal' | 'expanded';
  fontSize: 'small' | 'medium' | 'large';
}

/**
 * Preferências de comportamento — notificações, som e modos de interação.
 */
export interface BehaviorPreferences {
  sound: boolean;
  chatNotify: boolean;
  quickReply: boolean;
  confirmBulk: boolean;
  ticketOpenMode: 'modal' | 'page';
}

/**
 * Preferências padrão do CRM — aplicadas ao criar entidades CRM.
 */
export interface CrmDefaultsPreferences {
  negotiationType: 'basic' | 'advanced' | 'full';
  taskStatus: 'pending' | 'in_progress' | 'done';
  pipelineView: 'kanban' | 'list';
  negotiationOrder: 'date' | 'value' | 'probability';
}

/**
 * Preferências de segurança — gerenciamento de sessão.
 */
export interface SecurityPreferences {
  sessionTimeout: number | null;
}

/**
 * Preferências de acessibilidade — alto contraste e movimento reduzido.
 */
export interface AccessibilityPreferences {
  highContrast: boolean;
  reducedMotion: boolean;
}

/**
 * Estrutura completa de preferências do usuário persistida no backend.
 */
export interface UserPreferences {
  appearance: AppearancePreferences;
  behavior: BehaviorPreferences;
  crmDefaults: CrmDefaultsPreferences;
  security: SecurityPreferences;
  accessibility: AccessibilityPreferences;
}

/**
 * Envelope de resposta da API para preferências do usuário.
 */
export interface UserPreferencesResponse {
  data: UserPreferences;
}

/**
 * Registro individual de preferência de notificação retornado pelo backend.
 * Espelha o model `ConfigurationNotificationPreference`.
 */
export interface NotificationPreference {
  id: string;
  notification_type: string;
  channels: string[];
  enabled: boolean;
  quiet_start: string | null;
  quiet_end: string | null;
}

/**
 * Resposta completa de GET /configuration/notifications/preferences.
 */
export interface NotificationPreferencesResponse {
  data: NotificationPreference[];
  types: Record<string, string>;
  channels: Record<string, string>;
}

/**
 * Payload para PUT /configuration/notifications/preferences (atualização em massa).
 */
export interface NotificationPreferencesBulkPayload {
  preferences: {
    type: string;
    channels: string[];
    enabled: boolean;
  }[];
}
