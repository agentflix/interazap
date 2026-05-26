/**
 * Modelo de integração/ChatInstance.
 * Espelha a entidade de integração de canal retornada pelo backend em `/channels`.
 */

/** Sub-objeto de configurações da integração com configurações específicas do canal. */
export interface IntegrationSettings {
  channel_provider_id: number;
  cellphone: string;
  instance?: string;
  client_token?: string;
  token?: string;
  phone_number_id?: string;
  access_token?: string;
  bot_token?: string;
  channel_fallback_message?: string;
  send_attendant_name?: boolean;
  send_outside_business_hours_message?: boolean;
  outside_business_hours_message?: string;
  send_no_business_hours_message?: boolean;
  no_business_hours_message?: string;
  send_department_transfer_message?: boolean;
  department_transfer_message?: string;
  send_start_service_message?: boolean;
  start_service_message?: string;
  send_end_service_message?: boolean;
  end_service_message?: string;
  /** Override tenant-level auto-close. null = inherits tenant default. */
  auto_close_enabled?: boolean | null;
  auto_close_after_minutes?: number | null;
  auto_close_target?: 'both' | 'client' | 'agent' | null;
  auto_close_message?: string | null;
}

/** Entidade de integração representando um canal de chat conectado (WhatsApp, Telegram, etc.). */
export interface Integration {
  id: string;
  name: string;
  provider: string;
  is_active: boolean;
  is_connected?: boolean;
  has_token?: boolean;
  evaluation_enabled?: boolean;
  evaluation_cutoff_score?: number;
  /** Override tenant-level auto-close. null = inherits tenant default. */
  auto_close_enabled?: boolean | null;
  auto_close_after_minutes?: number | null;
  auto_close_target?: 'both' | 'client' | 'agent' | null;
  auto_close_message?: string | null;
  status?: string;
  integration_id?: string | number;
  settings: IntegrationSettings;
  token?: string;
  connection_status?: string;
  created_at?: string;
  updated_at?: string;
}

export type IntegrationConnectionUiState =
  | 'connected'
  | 'disconnected'
  | 'connecting'
  | 'qr'
  | 'unknown';

export interface IntegrationConnectionStateSource {
  is_connected?: boolean;
  connection_status?: string | null;
  connected?: boolean;
  status?: string | null;
  qrcode?: string | null;
  paircode?: string | null;
}

export interface IntegrationFilters {
  search?: string;
  is_active?: boolean;
  page?: number;
  per_page?: number;
  sort_by?: string;
  sort_dir?: 'asc' | 'desc';
}

export interface IntegrationConnectPayload {
  mode: 'qr' | 'pair';
  phone?: string | null;
}

export interface IntegrationConnectResponse {
  instance: Integration;
  connection: {
    mode: 'qr' | 'pair';
    qr_code?: string | null;
    pair_code?: string | null;
    expires_at?: string | null;
  };
}

export interface IntegrationStatusResponse {
  instance: Integration;
  status: Record<string, string | number | boolean | null>;
}
