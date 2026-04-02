/**
 * User preferences model.
 * Mirrors the backend `preferences` JSONB stored in `auth_users`.
 */

/**
 * Appearance preferences — theme, density, and font size.
 */
export interface AppearancePreferences {
  theme: 'light' | 'dark' | 'system';
  density: 'compact' | 'normal' | 'expanded';
  fontSize: 'small' | 'medium' | 'large';
}

/**
 * Behavior preferences — notifications, sound, and interaction modes.
 */
export interface BehaviorPreferences {
  sound: boolean;
  chatNotify: boolean;
  quickReply: boolean;
  confirmBulk: boolean;
  ticketOpenMode: 'modal' | 'page';
}

/**
 * CRM default preferences — defaults applied when creating CRM entities.
 */
export interface CrmDefaultsPreferences {
  negotiationType: 'basic' | 'advanced' | 'full';
  taskStatus: 'pending' | 'in_progress' | 'done';
  pipelineView: 'kanban' | 'list';
  negotiationOrder: 'date' | 'value' | 'probability';
}

/**
 * Security preferences — session management.
 */
export interface SecurityPreferences {
  sessionTimeout: number | null;
}

/**
 * Accessibility preferences — high contrast and reduced motion.
 */
export interface AccessibilityPreferences {
  highContrast: boolean;
  reducedMotion: boolean;
}

/**
 * Complete user preferences structure persisted in the backend.
 */
export interface UserPreferences {
  appearance: AppearancePreferences;
  behavior: BehaviorPreferences;
  crmDefaults: CrmDefaultsPreferences;
  security: SecurityPreferences;
  accessibility: AccessibilityPreferences;
}

/**
 * API response envelope for user preferences.
 */
export interface UserPreferencesResponse {
  data: UserPreferences;
}

/**
 * A single notification preference record returned by the backend.
 * Mirrors `ConfigurationNotificationPreference` model.
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
 * Full response from GET /configuration/notifications/preferences.
 */
export interface NotificationPreferencesResponse {
  data: NotificationPreference[];
  types: Record<string, string>;
  channels: Record<string, string>;
}

/**
 * Payload for PUT /configuration/notifications/preferences (bulk update).
 */
export interface NotificationPreferencesBulkPayload {
  preferences: {
    type: string;
    channels: string[];
    enabled: boolean;
  }[];
}
