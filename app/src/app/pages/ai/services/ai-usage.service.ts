import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable, signal } from '@angular/core';
import { type Observable, map, tap } from 'rxjs';
import { environment } from '@env/environment';
import {
  type UsageSummary,
  type UsageDaily,
  type UsageAgent,
  type UsageMonthly,
  type UsageBudgetStatus,
  type UsageVoice,
  type AiNotification,
} from '@ai/models/ai.model';

/**
 * Service para métricas de uso e custos da IA.
 *
 * Fornece dados agregados de consumo de tokens, custos,
 * e notificações de sellers para dashboards e relatórios.
 *
 * @class AiUsageService
 */
@Injectable({ providedIn: 'root' })
export class AiUsageService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/ai/usage`;
  private readonly notificationsUrl = `${environment.apiUrl}/ai/notifications`;

  /** Contador reativo de notificações não lidas */
  readonly unreadCount = signal<number>(0);

  /**
   * Obtém resumo de uso do período atual.
   */
  getSummary(): Observable<UsageSummary> {
    return this.http
      .get<{ data: UsageSummary }>(`${this.baseUrl}/summary`)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Obtém breakdown diário de uso.
   * @param days Número de dias (default 30)
   */
  getDaily(days = 30): Observable<UsageDaily[]> {
    const params = new HttpParams().set('days', String(days));
    return this.http
      .get<{ data: UsageDaily[] }>(`${this.baseUrl}/daily`, { params })
      .pipe(map((resp) => resp.data));
  }

  /**
   * Obtém ranking dos agentes por custo.
   * @param limit Limite de resultados (default 10)
   */
  getTopAgents(limit = 10): Observable<UsageAgent[]> {
    const params = new HttpParams().set('limit', String(limit));
    return this.http
      .get<{ data: UsageAgent[] }>(`${this.baseUrl}/top-agents`, { params })
      .pipe(map((resp) => resp.data));
  }

  /**
   * Obtém histórico mensal de uso.
   * @param months Número de meses (default 6)
   */
  getMonthlyHistory(months = 6): Observable<UsageMonthly[]> {
    const params = new HttpParams().set('months', String(months));
    return this.http
      .get<{ data: UsageMonthly[] }>(`${this.baseUrl}/monthly`, { params })
      .pipe(map((resp) => resp.data));
  }

  /**
   * Obtém status do budget de tokens (diário e por run).
   */
  getBudgetStatus(): Observable<UsageBudgetStatus> {
    return this.http
      .get<{ data: UsageBudgetStatus }>(`${this.baseUrl}/budget`)
      .pipe(map((resp) => resp.data));
  }

  /**
   * Obtém métricas de uso de voz (STT/TTS).
   * @param days Número de dias (default 30)
   */
  getVoiceUsage(days = 30): Observable<UsageVoice> {
    const params = new HttpParams().set('days', String(days));
    return this.http
      .get<{ data: UsageVoice }>(`${this.baseUrl}/voice`, { params })
      .pipe(map((resp) => resp.data));
  }

  // ===== Notifications =====

  /**
   * Lista notificações do usuário atual.
   * @param unreadOnly Filtrar apenas não lidas
   */
  getNotifications(unreadOnly = false): Observable<AiNotification[]> {
    let params = new HttpParams();
    if (unreadOnly) params = params.set('unread_only', 'true');

    return this.http
      .get<{ data: AiNotification[] }>(this.notificationsUrl, { params })
      .pipe(map((resp) => resp.data));
  }

  /**
   * Obtém contagem de notificações não lidas e atualiza o signal.
   */
  refreshUnreadCount(): Observable<number> {
    return this.http
      .get<{ data: { unread_count: number } }>(`${this.notificationsUrl}/unread-count`)
      .pipe(
        map((resp) => resp.data.unread_count),
        tap((count) => this.unreadCount.set(count)),
      );
  }

  /**
   * Marca notificações como lidas.
   * @param ids IDs das notificações (vazio = todas)
   */
  markAsRead(ids: string[] = []): Observable<{ updated_count: number }> {
    return this.http
      .post<{ updated_count: number }>(`${this.notificationsUrl}/mark-read`, { ids })
      .pipe(tap(() => this.refreshUnreadCount().subscribe()));
  }

  /**
   * Busca uma notificação específica.
   * @param id ID da notificação
   */
  getNotification(id: string): Observable<AiNotification> {
    return this.http
      .get<{ data: AiNotification }>(`${this.notificationsUrl}/${id}`)
      .pipe(map((resp) => resp.data));
  }
}
