import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import { type DashboardData } from '../models/dashboard.model';

/**
 * Service de agregação de dados do dashboard.
 *
 * Fornece KPIs e métricas para a visão principal do dashboard,
 * incluindo receita, pipeline, tickets, CSAT e atividades.
 */
@Injectable({ providedIn: 'root' })
export class DashboardService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/dashboard`;

  /**
   * Recupera dados agregados do dashboard para um intervalo de datas ou período.
   * @param dateFrom Data de início no formato ISO 8601 (AAAA-MM-DD)
   * @param dateTo Data de fim no formato ISO 8601 (AAAA-MM-DD)
   * @param period Quantidade de dias (alternativa a dateFrom/dateTo)
   * @returns Observable com a resposta de dados do dashboard
   */
  getData(
    dateFrom?: string,
    dateTo?: string,
    period?: number,
  ): Observable<{ data: DashboardData }> {
    let params = new HttpParams();

    if (dateFrom && dateTo) {
      params = params.set('date_from', dateFrom).set('date_to', dateTo);
    } else if (period) {
      params = params.set('period', String(period));
    } else {
      params = params.set('period', '30'); // Fallback padrão
    }

    return this.http.get<{ data: DashboardData }>(this.baseUrl, { params });
  }
}
