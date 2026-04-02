import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable, map } from 'rxjs';
import { environment } from '@env/environment';

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

/**
 * Service for managing UAZAPI WhatsApp instances.
 *
 * @remarks
 * Provides CRUD operations for WhatsApp instance lifecycle including
 * connect, disconnect, status monitoring, and profile management.
 *
 * @example
 * ```typescript
 * const service = inject(UazapiInstancesService);
 * service.list({ status: 'connected' }).subscribe();
 * ```
 */
@Injectable({ providedIn: 'root' })
export class UazapiInstancesService {
  private readonly baseUrl = `${environment.apiUrl}/platform/uazapi/instances`;
  private readonly http = inject(HttpClient);

  /**
   * Lists instances with optional filters and pagination.
   *
   * @param filters - Optional filters (search, status, pagination)
   * @returns Observable with paginated and normalized instance list
   */
  list(filters: UazapiInstanceFilters = {}): Observable<Paginated<UazapiInstance>> {
    let params = new HttpParams();
    if (typeof filters.search === 'string' && filters.search.length > 0) {
      params = params.set('search', filters.search);
    }
    if (filters.status !== undefined && filters.status !== 'all') {
      params = params.set('status', filters.status);
    }
    if (typeof filters.page === 'number') {
      params = params.set('page', String(filters.page));
    }
    if (typeof filters.per_page === 'number') {
      params = params.set('per_page', String(filters.per_page));
    }

    return this.http
      .get<Paginated<UazapiInstance>>(this.baseUrl, { params })
      .pipe(map((resp) => this.normalize(resp)));
  }

  /**
   * Creates a new UAZAPI instance.
   *
   * @param payload - Instance creation data (name, system_name, config)
   * @returns Observable with created instance
   */
  create(payload: CreateUazapiInstancePayload): Observable<UazapiInstance> {
    return this.http
      .post<ApiResponse<UazapiInstance>>(this.baseUrl, payload)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Updates admin-level configuration fields for an instance.
   *
   * @param id - Instance identifier
   * @param payload - Admin fields to update
   * @returns Observable with updated instance
   */
  updateAdminFields(id: string, payload: UpdateAdminFieldsPayload): Observable<UazapiInstance> {
    return this.http
      .patch<ApiResponse<UazapiInstance>>(`${this.baseUrl}/${id}/admin-fields`, payload)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Updates the display name of an instance.
   *
   * @param id - Instance identifier
   * @param name - New display name
   * @returns Observable with updated instance
   */
  updateName(id: string, name: string): Observable<UazapiInstance> {
    return this.http
      .patch<ApiResponse<UazapiInstance>>(`${this.baseUrl}/${id}/name`, { name })
      .pipe(map((resp) => resp.data));
  }

  /**
   * Initiates connection to the WhatsApp instance (QR code or pairing).
   *
   * @param id - Instance identifier
   * @param payload - Connection mode and optional phone number
   * @returns Observable with connection response and instance data
   */
  connect(id: string, payload: ConnectInstancePayload): Observable<ConnectInstanceResponse> {
    return this.http
      .post<ApiResponse<ConnectInstanceResponse>>(`${this.baseUrl}/${id}/connect`, payload)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Disconnects the WhatsApp instance session.
   *
   * @param id - Instance identifier
   * @returns Observable with disconnection response
   */
  disconnect(id: string): Observable<DisconnectInstanceResponse> {
    return this.http
      .post<ApiResponse<DisconnectInstanceResponse>>(`${this.baseUrl}/${id}/disconnect`, {})
      .pipe(map((resp) => resp.data));
  }

  /**
   * Retrieves current runtime status of the instance.
   *
   * @param id - Instance identifier
   * @returns Observable with status response
   */
  status(id: string): Observable<StatusInstanceResponse> {
    return this.http
      .get<ApiResponse<StatusInstanceResponse>>(`${this.baseUrl}/${id}/status`)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Deletes an instance.
   *
   * @param id - Instance identifier
   * @returns Observable completing on deletion
   */
  delete(id: string): Observable<null> {
    return this.http.delete<ApiResponse<null>>(`${this.baseUrl}/${id}`).pipe(map(() => null));
  }

  /**
   * Updates the WhatsApp profile image for an instance.
   *
   * @param id - Instance identifier
   * @param image - Base64 encoded image data
   * @returns Observable with updated instance
   */
  updateProfileImage(id: string, image: string): Observable<UazapiInstance> {
    return this.http
      .post<ApiResponse<ProfileImageResponse>>(`${this.baseUrl}/${id}/profile-image`, { image })
      .pipe(map((resp) => resp.data.instance));
  }

  /**
   * Sets the presence status (available/unavailable) for an instance.
   *
   * @param id - Instance identifier
   * @param presence - Presence value ('available' or 'unavailable')
   * @returns Observable with updated instance
   */
  updatePresence(id: string, presence: 'available' | 'unavailable'): Observable<UazapiInstance> {
    return this.http
      .post<ApiResponse<PresenceResponse>>(`${this.baseUrl}/${id}/presence`, { presence })
      .pipe(map((resp) => resp.data.instance));
  }

  private normalize(resp: Paginated<UazapiInstance>): Paginated<UazapiInstance> {
    return {
      data: resp.data ?? [],
      meta: resp.meta ?? {
        current_page: 1,
        total: (resp.data ?? []).length,
        per_page: (resp.data ?? []).length,
        last_page: 1,
      },
    };
  }
}
