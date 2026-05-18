/**
 * Scheduling settings model.
 * Mirrors the backend `configuration_scheduling_settings` table.
 */

/**
 * Configuration for event confirmation and reminder behavior.
 */
export interface SchedulingSettings {
  id: string;
  tenant_id: string;
  event_confirmation_advance_minutes: number;
  event_confirmation_enabled: boolean;
  event_confirmation_notify_ui: boolean;
  event_confirmation_notify_push: boolean;
  created_at: string;
  updated_at: string;
}

/**
 * API response envelope for scheduling settings.
 */
export interface SchedulingSettingsResponse {
  success: boolean;
  message: string;
  data: SchedulingSettings;
}
