import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import type { Observable } from 'rxjs';
import { environment } from '@env/environment';
import type {
  NotificationPreference,
  NotificationPreferencesResponse,
  NotificationPreferencesBulkPayload,
} from '@shared/models/preferences.model';

/**
 * Gerencia as preferências de notificação do usuário autenticado por tipo e canal.
 */
@Injectable({ providedIn: 'root' })
export class NotificationPreferencesService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/notifications/preferences`;

  /**
   * Retorna as preferências de notificação do usuário atual para todos os tipos e canais.
   * @returns Observable com preferências agrupadas por tipo e canal
   */
  getPreferences(): Observable<NotificationPreferencesResponse> {
    return this.http.get<NotificationPreferencesResponse>(this.baseUrl);
  }

  /**
   * Atualiza em lote as preferências de notificação do usuário atual.
   *
   * @param payload Array de objetos de preferência por tipo e canal
   * @returns Observable com preferências atualizadas
   */
  updateAllPreferences(payload: NotificationPreferencesBulkPayload): Observable<{ data: NotificationPreference[] }> {
    return this.http.put<{ data: NotificationPreference[] }>(this.baseUrl, payload);
  }
}
