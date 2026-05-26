import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import {
  type TenantSettings,
  type TenantSettingsResponse,
} from '@shared/models/tenant-settings.model';

/**
 * Consulta e atualiza configurações de nível de tenant.
 *
 * Contexto: service HTTP acessível apenas por admins/managers com permissão
 * `platform.tenants.manage`. Endpoints em /platform/tenants/{id}/settings.
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
   * Busca as configurações de um tenant específico.
   *
   * @param tenantId - UUID do tenant
   * @returns Observable com as configurações do tenant
   */
  getSettings(tenantId: string): Observable<TenantSettingsResponse> {
    return this.http.get<TenantSettingsResponse>(`${this.baseUrl}/${tenantId}/settings`);
  }

  /**
   * Atualiza parcialmente as configurações de um tenant específico.
   * O backend realiza deep merge preservando seções não alteradas.
   *
   * @param tenantId - UUID do tenant
   * @param data - Objeto parcial de configurações para mesclar com valores existentes
   * @returns Observable com as configurações atualizadas
   */
  updateSettings(
    tenantId: string,
    data: Partial<TenantSettings>,
  ): Observable<TenantSettingsResponse> {
    return this.http.patch<TenantSettingsResponse>(`${this.baseUrl}/${tenantId}/settings`, data);
  }
}
