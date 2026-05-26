import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import { type PaginatedResponse } from '@core/models/pagination.model';
import {
  type Integration,
  type IntegrationConnectPayload,
  type IntegrationConnectResponse,
  type IntegrationConnectionStateSource,
  type IntegrationConnectionUiState,
  type IntegrationFilters,
  type IntegrationSettings,
  type IntegrationStatusResponse,
} from '@shared/models/integration.model';
export type { IntegrationConnectPayload, IntegrationConnectResponse, IntegrationConnectionStateSource, IntegrationConnectionUiState, IntegrationFilters, IntegrationStatusResponse } from '@shared/models/integration.model';

export type { Integration, IntegrationSettings };


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

/** Normaliza valores de status de conexão do backend para decisões de UI. */
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

/** Retorna se a integração deve ser tratada como conectada na UI. */
export function isIntegrationConnected(integration: IntegrationConnectionStateSource): boolean {
  if (integration.connected === true) {
    return true;
  }

  if (typeof integration.is_connected === 'boolean') {
    return integration.is_connected;
  }

  return CONNECTED_CONNECTION_STATUSES.has(resolveIntegrationConnectionStatus(integration));
}

/** Resolve o estado estável de UI usando `is_connected` primeiro e o status textual como fallback. */
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

/** Retorna se um evento de conexão em tempo real deve ignorar o buffer e ser processado imediatamente. */
export function shouldFlushIntegrationConnectionImmediately(
  integration: IntegrationConnectionStateSource,
): boolean {
  const uiState = getIntegrationConnectionUiState(integration);

  return uiState === 'connected' || uiState === 'disconnected' || uiState === 'qr';
}

/** Query parameters for integrations list. */

/** Payload to establish integration connection. */

/** Connect response payload. */

/** Runtime status payload. */

/** Gerencia integrações de chat com operações de CRUD e ciclo de vida de conexão. */
@Injectable({ providedIn: 'root' })
export class IntegrationService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/channels`;

  /**
   * Lista integrações com filtros opcionais e paginação.
   * @param filters Filtros: search, is_active, paginação, ordenação
   * @returns Observable com lista paginada de integrações
   */
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

  /** Cria uma nova integração de canal. */
  create(data: Partial<Integration>): Observable<{ data: Integration }> {
    return this.http.post<{ data: Integration }>(this.baseUrl, data);
  }

  /** Atualiza configurações de uma integração existente. */
  update(id: string, data: Partial<Integration>): Observable<{ data: Integration }> {
    return this.http.put<{ data: Integration }>(`${this.baseUrl}/${id}`, data);
  }

  /** Exclui permanentemente uma integração. */
  delete(id: string | number): Observable<void> {
    return this.http.delete<void>(`${this.baseUrl}/${id}`);
  }

  /** Retorna uma integração pelo ID. */
  find(id: string): Observable<{ data: Integration }> {
    return this.http.get<{ data: Integration }>(`${this.baseUrl}/${id}`);
  }

  /** Alterna o estado ativo/inativo de uma integração. */
  toggleActive(id: string | number): Observable<void> {
    return this.http.patch<void>(`${this.baseUrl}/${id}/toggle-active`, {});
  }

  /** Desconecta a sessão ativa da integração (ex.: QR Code WhatsApp). */
  disconnect(id: string | number): Observable<void> {
    return this.http.post<void>(`${this.baseUrl}/${id}/disconnect`, {});
  }

  /**
   * Inicia o fluxo de conexão e retorna detalhes de QR/pareamento.
   * @param id ID da integração
   * @param payload Dados de conexão (ex.: token, configurações)
   * @returns Observable com resposta de conexão (QR code, status)
   */
  connect(
    id: string,
    payload: IntegrationConnectPayload,
  ): Observable<{ data: IntegrationConnectResponse }> {
    return this.http.post<{ data: IntegrationConnectResponse }>(
      `${this.baseUrl}/${id}/connect`,
      payload,
    );
  }

  /** Retorna o status atual da conexão com o provedor externo. */
  status(id: string): Observable<{ data: IntegrationStatusResponse }> {
    return this.http.get<{ data: IntegrationStatusResponse }>(`${this.baseUrl}/${id}/status`);
  }
}
