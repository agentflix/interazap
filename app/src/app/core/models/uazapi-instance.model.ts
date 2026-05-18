export interface Paginated<T> {
  data: T[];
  meta: {
    current_page: number;
    total: number;
    per_page: number;
    last_page: number;
  };
}

export type UazapiInstanceStatus = 'connected' | 'disconnected' | 'connecting' | 'qr' | 'all';

export interface ApiResponse<T> {
  success: boolean;
  message: string;
  data: T;
}

export interface UazapiInstance {
  id: string;
  name: string | null;
  system_name: string | null;
  token?: string | null;
  config?: Record<string, string | null> | null;
  status?: string | null;
  last_seen_at?: string | null;
  updated_at?: string | null;
  token_preview?: string | null;
  metadata?: Record<string, unknown> | null;
}

export interface UazapiInstanceFilters {
  search?: string | undefined;
  status?: UazapiInstanceStatus;
  page?: number;
  per_page?: number;
}

export interface CreateUazapiInstancePayload {
  name: string;
  system_name: string;
  config?: Record<string, string | null> | null;
}

export interface UpdateAdminFieldsPayload {
  config: Record<string, string | null>;
}

export interface ConnectInstancePayload {
  mode: 'qr' | 'pair';
  phone?: string | null;
}

export interface ConnectInstanceResponse {
  instance: UazapiInstance;
  connection: Record<string, unknown>;
}

export interface StatusInstanceResponse {
  instance: UazapiInstance;
  status: Record<string, unknown>;
}

export interface DisconnectInstanceResponse {
  instance: UazapiInstance;
  connection: Record<string, unknown>;
}

export interface ProfileImageResponse {
  instance: UazapiInstance;
  response: Record<string, unknown>;
}

export interface PresenceResponse {
  instance: UazapiInstance;
  response: Record<string, unknown>;
}
