import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';

/** Integration entity used by transmission list form. */
export interface Integration {
  id: string;
  name: string;
  provider: string;
  is_active: boolean;
  is_connected?: boolean;
  has_token?: boolean;
  evaluation_enabled?: boolean;
  evaluation_cutoff_score?: number;
  status?: string;
  integration_id?: string | number;
  settings: {
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
  };
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

const CONNECTED_CONNECTION_STATUSES = new Set([
  'connected',
  'open',
  'online',
  'ready',
  'authorized',
  'authenticated',
  'authenticated_connected',
  'conectado',
]);

const QR_CONNECTION_STATUSES = new Set(['qr', 'qrcode', 'pairing']);

const CONNECTING_CONNECTION_STATUSES = new Set(['connecting', 'pending', 'initializing']);

const DISCONNECTED_CONNECTION_STATUSES = new Set([
  'disconnected',
  'offline',
  'close',
  'closed',
  'unauthorized',
]);

/** Normalizes backend connection status values for UI decisions. */
export function normalizeIntegrationConnectionStatus(status: string | null | undefined): string {
  return typeof status === 'string' ? status.trim().toLowerCase() : '';
}

function resolveIntegrationConnectionStatus(source: IntegrationConnectionStateSource): string {
  return normalizeIntegrationConnectionStatus(source.connection_status ?? source.status);
}

function hasIntegrationConnectionQrPayload(source: IntegrationConnectionStateSource): boolean {
  return [source.qrcode, source.paircode].some(
    (value) => typeof value === 'string' && value.trim().length > 0,
  );
}

/** Returns whether the integration should be treated as connected in the UI. */
export function isIntegrationConnected(integration: IntegrationConnectionStateSource): boolean {
  if (integration.connected === true) {
    return true;
  }

  if (typeof integration.is_connected === 'boolean') {
    return integration.is_connected;
  }

  return CONNECTED_CONNECTION_STATUSES.has(resolveIntegrationConnectionStatus(integration));
}

/** Resolves a stable UI state using is_connected first and textual status as fallback. */
export function getIntegrationConnectionUiState(
  integration: IntegrationConnectionStateSource,
): IntegrationConnectionUiState {
  const normalizedStatus = resolveIntegrationConnectionStatus(integration);

  if (isIntegrationConnected(integration)) {
    return 'connected';
  }

  if (
    hasIntegrationConnectionQrPayload(integration) ||
    QR_CONNECTION_STATUSES.has(normalizedStatus)
  ) {
    return 'qr';
  }

  if (CONNECTING_CONNECTION_STATUSES.has(normalizedStatus)) {
    return 'connecting';
  }

  if (
    integration.connected === false ||
    typeof integration.is_connected === 'boolean' ||
    DISCONNECTED_CONNECTION_STATUSES.has(normalizedStatus)
  ) {
    return 'disconnected';
  }

  return 'unknown';
}

/** Returns whether a realtime connection event should bypass buffering. */
export function shouldFlushIntegrationConnectionImmediately(
  integration: IntegrationConnectionStateSource,
): boolean {
  const uiState = getIntegrationConnectionUiState(integration);

  return uiState === 'connected' || uiState === 'disconnected' || uiState === 'qr';
}

/** Query parameters for integrations list. */
export interface IntegrationFilters {
  search?: string;
  is_active?: boolean;
  page?: number;
  per_page?: number;
  sort_by?: string;
  sort_dir?: 'asc' | 'desc';
}

/** Generic paginated response used by integrations endpoint. */
export interface PaginatedResponse<T> {
  data: T[];
  meta: {
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
  };
}

/** Payload to establish integration connection. */
export interface IntegrationConnectPayload {
  mode: 'qr' | 'pair';
  phone?: string | null;
}

/** Connect response payload. */
export interface IntegrationConnectResponse {
  instance: Integration;
  connection: {
    mode: 'qr' | 'pair';
    qr_code?: string | null;
    pair_code?: string | null;
    expires_at?: string | null;
  };
}

/** Runtime status payload. */
export interface IntegrationStatusResponse {
  instance: Integration;
  status: Record<string, string | number | boolean | null>;
}

/** Service for chat integrations CRUD and connection lifecycle. */
@Injectable({ providedIn: 'root' })
export class IntegrationService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/channels`;

  /** Lists integrations with optional filters. */
  list(filters: IntegrationFilters = {}): Observable<PaginatedResponse<Integration>> {
    let params = new HttpParams();

    if (filters.search) {
      params = params.set('search', filters.search);
    }
    if (filters.is_active !== undefined) {
      params = params.set('is_active', String(filters.is_active));
    }
    if (filters.page) {
      params = params.set('page', String(filters.page));
    }
    if (filters.per_page) {
      params = params.set('per_page', String(filters.per_page));
    }
    if (filters.sort_by) {
      params = params.set('sort_by', filters.sort_by);
    }
    if (filters.sort_dir) {
      params = params.set('sort_dir', filters.sort_dir);
    }

    return this.http.get<PaginatedResponse<Integration>>(this.baseUrl, { params });
  }

  /** Creates a new integration. */
  create(data: Partial<Integration>): Observable<{ data: Integration }> {
    return this.http.post<{ data: Integration }>(this.baseUrl, data);
  }

  /** Updates an integration. */
  update(id: string, data: Partial<Integration>): Observable<{ data: Integration }> {
    return this.http.put<{ data: Integration }>(`${this.baseUrl}/${id}`, data);
  }

  /** Deletes an integration. */
  delete(id: string | number): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/${id}`);
  }

  /** Finds one integration by id. */
  find(id: string): Observable<{ data: Integration }> {
    return this.http.get<{ data: Integration }>(`${this.baseUrl}/${id}`);
  }

  /** Toggles active/inactive state. */
  toggleActive(id: string | number): Observable<void> {
    return this.http.patch<void>(`${this.baseUrl}/${id}/toggle-active`, {});
  }

  /** Disconnects integration session. */
  disconnect(id: string | number): Observable<void> {
    return this.http.post<void>(`${this.baseUrl}/${id}/disconnect`, {});
  }

  /** Starts connection flow and returns QR/pair details. */
  connect(
    id: string,
    payload: IntegrationConnectPayload,
  ): Observable<{ data: IntegrationConnectResponse }> {
    return this.http.post<{ data: IntegrationConnectResponse }>(
      `${this.baseUrl}/${id}/connect`,
      payload,
    );
  }

  /** Returns current external provider status. */
  status(id: string): Observable<{ data: IntegrationStatusResponse }> {
    return this.http.get<{ data: IntegrationStatusResponse }>(`${this.baseUrl}/${id}/status`);
  }
}
