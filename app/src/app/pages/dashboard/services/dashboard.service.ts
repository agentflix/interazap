import { HttpClient, HttpParams } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import { type DashboardData } from '../models/dashboard.model';

/**
 * Service for dashboard data aggregation.
 *
 * Provides KPIs and metrics for the main dashboard view,
 * including revenue, pipeline, tickets, CSAT, and activity data.
 *
 * @class DashboardService
 */
@Injectable({ providedIn: 'root' })
export class DashboardService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/dashboard`;

  /**
   * Retrieves aggregated dashboard data for a given date range or period.
   *
   * @param dateFrom - Start date in ISO 8601 format (YYYY-MM-DD)
   * @param dateTo - End date in ISO 8601 format (YYYY-MM-DD)
   * @param period - Number of days (alternative to dateFrom/dateTo)
   * @returns Observable with the dashboard data response
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
      params = params.set('period', '30'); // Default fallback
    }

    return this.http.get<{ data: DashboardData }>(this.baseUrl, { params });
  }
}
