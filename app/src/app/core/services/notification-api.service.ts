import { inject, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import {
  type Notification,
  type NotificationListResponse,
} from '@shared/models/notification.model';

/**
 * Servico de integracao com a API de notificacoes do backend.
 * Gerencia fetching, leitura individual e leitura em massa de notificacoes.
 *
 * @class NotificationApiService
 * @example
 * ```ts
 * const api = inject(NotificationApiService);
 * api.fetchUnread(10).subscribe(notifications => { ... });
 * api.markAsRead('uuid-123').subscribe();
 * api.markAllAsRead().subscribe();
 * ```
 */
@Injectable({
  providedIn: 'root',
})
export class NotificationApiService {
  private readonly http = inject(HttpClient);

  private get baseUrl(): string {
    return `${environment.apiUrl}/notifications`;
  }

  /**
   * Busca a lista de notificacoes nao lidas do usuario autenticado.
   *
   * @param limit - Numero maximo de notificacoes a retornar (default: 10)
   * @returns Observable com lista de notificacoes e contagem de nao lidas
   */
  fetchUnread(limit = 10): Observable<NotificationListResponse> {
    return this.http.get<NotificationListResponse>(this.baseUrl, {
      params: { limit },
    });
  }

  /**
   * Marca uma notificacao especifica como lida.
   *
   * @param id - UUID da notificacao
   * @returns Observable vazio (204 No Content)
   */
  markAsRead(id: string): Observable<void> {
    return this.http.patch<void>(`${this.baseUrl}/${id}/read`, {});
  }

  /**
   * Marca todas as notificacoes do usuario como lidas.
   *
   * @returns Observable com contagem de notificacoes marcadas
   */
  markAllAsRead(): Observable<{ count: number; message: string }> {
    return this.http.post<{ count: number; message: string }>(`${this.baseUrl}/read-all`, {});
  }
}
