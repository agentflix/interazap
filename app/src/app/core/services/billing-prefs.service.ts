import { HttpClient } from '@angular/common/http';
import { inject, Injectable } from '@angular/core';
import type { Observable } from 'rxjs';
import { environment } from '@env/environment';

export interface PlatformTenantBillingPrefs {
  id: string;
  overage_mode_override: 'stop' | 'overage' | null;
}

interface ApiResponse<T> {
  data: T;
}

@Injectable({ providedIn: 'root' })
export class BillingPrefsService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/tenants/me`;

  updateOverageMode(
    mode: 'stop' | 'overage' | null,
  ): Observable<ApiResponse<PlatformTenantBillingPrefs>> {
    return this.http.patch<ApiResponse<PlatformTenantBillingPrefs>>(
      `${this.baseUrl}/billing-prefs`,
      { overage_mode_override: mode },
    );
  }
}
