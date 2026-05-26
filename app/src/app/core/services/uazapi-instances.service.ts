import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable, map } from 'rxjs';
import { environment } from '@env/environment';
import type { ApiResponse, ConnectInstancePayload, ConnectInstanceResponse, CreateUazapiInstancePayload, DisconnectInstanceResponse, Paginated, PresenceResponse, ProfileImageResponse, StatusInstanceResponse, UazapiInstance, UazapiInstanceFilters, UpdateAdminFieldsPayload } from '@core/models/uazapi-instance.model';
export type { ApiResponse, ConnectInstancePayload, ConnectInstanceResponse, CreateUazapiInstancePayload, DisconnectInstanceResponse, Paginated, PresenceResponse, ProfileImageResponse, StatusInstanceResponse, UazapiInstance, UazapiInstanceFilters, UazapiInstanceStatus, UpdateAdminFieldsPayload } from '@core/models/uazapi-instance.model';



/**
 * Gerencia instâncias UAZAPI do WhatsApp (ciclo de vida, conexão, status, perfil).
 *
 * Contexto: service HTTP para CRUD de instâncias WhatsApp com operações de
 * connect, disconnect, status monitoring e profile management.
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
   * Lista instâncias com filtros opcionais e paginação.
   *
   * @param filters - Filtros opcionais: search, status, paginação
   * @returns Observable com lista paginada e normalizada de instâncias
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
   * Cria uma nova instância UAZAPI.
   *
   * @param payload - Dados de criação: name, system_name, config
   * @returns Observable com a instância criada
   */
  create(payload: CreateUazapiInstancePayload): Observable<UazapiInstance> {
    return this.http
      .post<ApiResponse<UazapiInstance>>(this.baseUrl, payload)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Atualiza campos de configuração de nível admin de uma instância.
   *
   * @param id - Identificador da instância
   * @param payload - Campos admin para atualizar
   * @returns Observable com a instância atualizada
   */
  updateAdminFields(id: string, payload: UpdateAdminFieldsPayload): Observable<UazapiInstance> {
    return this.http
      .patch<ApiResponse<UazapiInstance>>(`${this.baseUrl}/${id}/admin-fields`, payload)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Atualiza o nome de exibição de uma instância.
   *
   * @param id - Identificador da instância
   * @param name - Novo nome de exibição
   * @returns Observable com a instância atualizada
   */
  updateName(id: string, name: string): Observable<UazapiInstance> {
    return this.http
      .patch<ApiResponse<UazapiInstance>>(`${this.baseUrl}/${id}/name`, { name })
      .pipe(map((resp) => resp.data));
  }

  /**
   * Inicia conexão com a instância WhatsApp (QR code ou pairing).
   *
   * @param id - Identificador da instância
   * @param payload - Modo de conexão e número de telefone opcional
   * @returns Observable com resposta de conexão e dados da instância
   */
  connect(id: string, payload: ConnectInstancePayload): Observable<ConnectInstanceResponse> {
    return this.http
      .post<ApiResponse<ConnectInstanceResponse>>(`${this.baseUrl}/${id}/connect`, payload)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Desconecta a sessão da instância WhatsApp.
   *
   * @param id - Identificador da instância
   * @returns Observable com resposta de desconexão
   */
  disconnect(id: string): Observable<DisconnectInstanceResponse> {
    return this.http
      .post<ApiResponse<DisconnectInstanceResponse>>(`${this.baseUrl}/${id}/disconnect`, {})
      .pipe(map((resp) => resp.data));
  }

  /**
   * Retorna o status de runtime atual da instância.
   *
   * @param id - Identificador da instância
   * @returns Observable com resposta de status
   */
  status(id: string): Observable<StatusInstanceResponse> {
    return this.http
      .get<ApiResponse<StatusInstanceResponse>>(`${this.baseUrl}/${id}/status`)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Exclui uma instância.
   *
   * @param id - Identificador da instância
   * @returns Observable que completa após a exclusão
   */
  delete(id: string): Observable<null> {
    return this.http.delete<ApiResponse<null>>(`${this.baseUrl}/${id}`).pipe(map(() => null));
  }

  /**
   * Atualiza a imagem de perfil do WhatsApp de uma instância.
   *
   * @param id - Identificador da instância
   * @param image - Dados da imagem em Base64
   * @returns Observable com a instância atualizada
   */
  updateProfileImage(id: string, image: string): Observable<UazapiInstance> {
    return this.http
      .post<ApiResponse<ProfileImageResponse>>(`${this.baseUrl}/${id}/profile-image`, { image })
      .pipe(map((resp) => resp.data.instance));
  }

  /**
   * Define o status de presença (disponível/indisponível) de uma instância.
   *
   * @param id - Identificador da instância
   * @param presence - Valor de presença ('available' ou 'unavailable')
   * @returns Observable com a instância atualizada
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
