import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import {
  type UserPreferences,
  type UserPreferencesResponse,
} from '@shared/models/preferences.model';

/**
 * Service for fetching and updating the authenticated user's preferences.
 *
 * @example
 * ```ts
 * const service = inject(PreferencesService);
 * service.getPreferences().subscribe(prefs => console.log(prefs));
 * ```
 */
@Injectable({ providedIn: 'root' })
export class PreferencesService {
  private readonly baseUrl = `${environment.apiUrl}/auth/profile/preferences`;
  private readonly http = inject(HttpClient);

  /**
   * Fetch the current user's preferences from the backend.
   * Returns full defaults for new users with no stored preferences.
   */
  getPreferences(): Observable<UserPreferencesResponse> {
    return this.http.get<UserPreferencesResponse>(this.baseUrl);
  }

  /**
   * Partially update the user's preferences.
   * The backend performs a deep merge so unchanged sections are preserved.
   *
   * @param data - Partial preferences object to merge with existing values
   */
  updatePreferences(data: Partial<UserPreferences>): Observable<UserPreferencesResponse> {
    return this.http.patch<UserPreferencesResponse>(this.baseUrl, data);
  }
}
