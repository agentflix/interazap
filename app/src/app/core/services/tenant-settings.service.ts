import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import {
  type TenantSettings,
  type TenantSettingsResponse,
} from '@shared/models/tenant-settings.model';

/**
 * Service for fetching and updating tenant-level settings.
 * Accessible only by admins/managers with `platform.tenants.manage` permission.
 *
 * @example
 * ```ts
 * const service = inject(TenantSettingsService);
 * service.getSettings('tenant-uuid').subscribe(settings => console.log(settings));
 * ```
 */
@Injectable({ providedIn: 'root' })
export class TenantSettingsService {
  private readonly baseUrl = `${environment.apiUrl}/platform/tenants`;
  private readonly http = inject(HttpClient);

  /**
   * Fetch the settings for a specific tenant.
   *
   * @param tenantId - UUID of the tenant
   */
  getSettings(tenantId: string): Observable<TenantSettingsResponse> {
    return this.http.get<TenantSettingsResponse>(`${this.baseUrl}/${tenantId}/settings`);
  }

  /**
   * Partially update the settings for a specific tenant.
   * The backend performs a deep merge so unchanged sections are preserved.
   *
   * @param tenantId - UUID of the tenant
   * @param data - Partial settings object to merge with existing values
   */
  updateSettings(
    tenantId: string,
    data: Partial<TenantSettings>,
  ): Observable<TenantSettingsResponse> {
    return this.http.patch<TenantSettingsResponse>(`${this.baseUrl}/${tenantId}/settings`, data);
  }
}
