import { Injectable, inject } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type {
  OpeningHour,
  OpeningHourResponse,
  OpeningHourListResponse,
  BulkUpdateOpeningHoursRequest,
} from '@core/models/opening-hour.model';

/**
 * Service for managing business opening hours.
 *
 * @remarks
 * Provides CRUD operations for defining weekly business hours schedules
 * and checking whether the business is currently open.
 *
 * @example
 * ```typescript
 * const service = inject(OpeningHourService);
 * service.isOpen().subscribe();
 * ```
 */
@Injectable({
  providedIn: 'root',
})
export class OpeningHourService {
  private readonly http = inject(HttpClient);
  private readonly apiUrl = `${environment.apiUrl}/opening-hours`;

  /**
   * Lists all opening hour entries for the tenant.
   *
   * @returns Observable with list of opening hours
   */
  list(): Observable<OpeningHourListResponse> {
    return this.http.get<OpeningHourListResponse>(this.apiUrl);
  }

  /**
   * Retrieves a single opening hour entry by ID.
   *
   * @param id - Opening hour identifier
   * @returns Observable with opening hour data
   */
  show(id: string): Observable<OpeningHourResponse> {
    return this.http.get<OpeningHourResponse>(`${this.apiUrl}/${id}`);
  }

  /**
   * Creates a new opening hour entry.
   *
   * @param data - Opening hour data (day_of_week, open_time, close_time, is_active)
   * @returns Observable with created opening hour
   */
  create(data: Partial<OpeningHour>): Observable<OpeningHourResponse> {
    return this.http.post<OpeningHourResponse>(this.apiUrl, data);
  }

  /**
   * Updates an existing opening hour entry.
   *
   * @param id - Opening hour identifier
   * @param data - Partial opening hour data to update
   * @returns Observable with updated opening hour
   */
  update(id: string, data: Partial<OpeningHour>): Observable<OpeningHourResponse> {
    return this.http.put<OpeningHourResponse>(`${this.apiUrl}/${id}`, data);
  }

  /**
   * Deletes an opening hour entry.
   *
   * @param id - Opening hour identifier
   * @returns Observable completing on deletion
   */
  delete(id: string): Observable<null> {
    return this.http.delete<null>(`${this.apiUrl}/${id}`);
  }

  /**
   * Performs a bulk update of all opening hours (replace all entries).
   *
   * @param data - Bulk update request with full opening hours array
   * @returns Observable with updated list of opening hours
   */
  bulkUpdate(data: BulkUpdateOpeningHoursRequest): Observable<OpeningHourListResponse> {
    return this.http.put<OpeningHourListResponse>(`${this.apiUrl}/bulk`, data);
  }

  /**
   * Checks whether the business is currently open based on configured hours.
   *
   * @returns Observable with is_open flag, current day, and current time
   */
  isOpen(): Observable<{
    success: boolean;
    data: { is_open: boolean; current_day: number; current_time: string };
  }> {
    return this.http.get<{
      success: boolean;
      data: { is_open: boolean; current_day: number; current_time: string };
    }>(`${this.apiUrl}/is-open`);
  }
}
