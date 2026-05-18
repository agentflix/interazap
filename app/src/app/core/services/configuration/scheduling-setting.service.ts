import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import { type SchedulingSettingsResponse } from '@core/models/configuration/scheduling-setting.model';

/**
 * Service for fetching and updating tenant scheduling settings.
 *
 * @example
 * ```ts
 * const service = inject(SchedulingSettingsService);
 * service.getSettings().subscribe(response => console.log(response.data));
 * ```
 */
@Injectable({ providedIn: 'root' })
export class SchedulingSettingsService {
  private readonly baseUrl = `${environment.apiUrl}/configuration/scheduling`;
  private readonly http = inject(HttpClient);

  /**
   * Fetch the scheduling settings for the authenticated tenant.
   */
  getSettings(): Observable<SchedulingSettingsResponse> {
    return this.http.get<SchedulingSettingsResponse>(this.baseUrl);
  }

  /**
   * Update the scheduling settings for the authenticated tenant.
   *
   * @param data - Partial settings object to update
   */
  updateSettings(
    data: Partial<SchedulingSettingsResponse['data']>,
  ): Observable<SchedulingSettingsResponse> {
    return this.http.put<SchedulingSettingsResponse>(this.baseUrl, data);
  }
}
