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
 * Service for managing per-user notification preferences.
 * Wraps the `GET /configuration/notifications/preferences` and
 * `PUT /configuration/notifications/preferences` endpoints of
 * `ConfigurationNotificationController`.
 */
@Injectable({ providedIn: 'root' })
export class NotificationPreferencesService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/configuration/notifications/preferences`;

  /**
   * Fetch the current user's notification preferences for all types and channels.
   */
  getPreferences(): Observable<NotificationPreferencesResponse> {
    return this.http.get<NotificationPreferencesResponse>(this.baseUrl);
  }

  /**
   * Bulk-update the current user's notification preferences.
   *
   * @param payload - Array of per-type preference objects
   */
  updateAllPreferences(payload: NotificationPreferencesBulkPayload): Observable<{ data: NotificationPreference[] }> {
    return this.http.put<{ data: NotificationPreference[] }>(this.baseUrl, payload);
  }
}
