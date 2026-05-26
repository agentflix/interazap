import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import { type SchedulingSettingsResponse } from '@core/models/configuration/scheduling-setting.model';

/**
 * Consulta e atualiza as configurações de agendamento do tenant autenticado.
 *
 * @example
 * ```ts
 * const service = inject(SchedulingSettingsService);
 * service.getSettings().subscribe(response => console.log(response.data));
 * ```
 */
@Injectable({ providedIn: 'root' })
export class SchedulingSettingsService {
  private readonly baseUrl = `${environment.apiUrl}/scheduling`;
  private readonly http = inject(HttpClient);

  /**
   * Retorna as configurações de agendamento do tenant autenticado.
   */
  getSettings(): Observable<SchedulingSettingsResponse> {
    return this.http.get<SchedulingSettingsResponse>(this.baseUrl);
  }

  /**
   * Atualiza as configurações de agendamento do tenant autenticado.
   *
   * @param data - Objeto parcial com as configurações a atualizar
   */
  updateSettings(
    data: Partial<SchedulingSettingsResponse['data']>,
  ): Observable<SchedulingSettingsResponse> {
    return this.http.put<SchedulingSettingsResponse>(this.baseUrl, data);
  }
}
