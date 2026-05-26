import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import {
  type UserPreferences,
  type UserPreferencesResponse,
} from '@shared/models/preferences.model';

/**
 * Consulta e atualiza as preferências do usuário autenticado.
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
   * Retorna as preferências do usuário atual do backend.
   * Devolve os padrões completos para novos usuários sem preferências salvas.
   */
  getPreferences(): Observable<UserPreferencesResponse> {
    return this.http.get<UserPreferencesResponse>(this.baseUrl);
  }

  /**
   * Atualiza parcialmente as preferências do usuário.
   * O backend realiza deep merge, preservando seções não alteradas.
   *
   * @param data - Objeto parcial de preferências a mesclar com os valores existentes
   */
  updatePreferences(data: Partial<UserPreferences>): Observable<UserPreferencesResponse> {
    return this.http.patch<UserPreferencesResponse>(this.baseUrl, data);
  }
}
