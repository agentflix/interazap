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

/**
 * Gerencia as preferências de cobrança do tenant autenticado.
 *
 * Permite configurar o modo de excedente (`overage_mode_override`):
 * `stop` para bloquear uso ao atingir cota, `overage` para cobrar excedente,
 * ou `null` para herdar o padrão do plano.
 */
@Injectable({ providedIn: 'root' })
export class BillingPrefsService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/tenants/me`;

  /**
   * Atualiza o modo de excedente de cobrança do tenant.
   *
   * @param mode `stop` bloqueia uso, `overage` cobra excedente, `null` usa padrão do plano
   * @returns Observable com as preferências atualizadas
   */
  updateOverageMode(
    mode: 'stop' | 'overage' | null,
  ): Observable<ApiResponse<PlatformTenantBillingPrefs>> {
    return this.http.patch<ApiResponse<PlatformTenantBillingPrefs>>(
      `${this.baseUrl}/billing-prefs`,
      { overage_mode_override: mode },
    );
  }
}
