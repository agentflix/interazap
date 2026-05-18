import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type { ProfileImageResponse, ProfileResponse, UpdatePasswordPayload, UpdateProfilePayload } from '@core/models/profile.model';
export type { ProfileImageResponse, ProfileResponse, ProfileUser, UpdatePasswordPayload, UpdateProfilePayload } from '@core/models/profile.model';



/**
 * Serviço para gestão do perfil do usuário autenticado.
 */
@Injectable({ providedIn: 'root' })
export class ProfileService {
  private readonly baseUrl = `${environment.apiUrl}/auth/profile`;
  private readonly http = inject(HttpClient);

  /** Obter dados do perfil autenticado. */
  getProfile(): Observable<ProfileResponse> {
    return this.http.get<ProfileResponse>(this.baseUrl);
  }

  /** Atualizar dados cadastrais do perfil. */
  updateProfile(payload: UpdateProfilePayload): Observable<ProfileResponse> {
    return this.http.put<ProfileResponse>(this.baseUrl, payload);
  }

  /** Atualizar senha do usuário. */
  updatePassword(payload: UpdatePasswordPayload): Observable<null> {
    return this.http.put<null>(`${this.baseUrl}/password`, payload);
  }

  /** Enviar novo avatar do usuário. */
  updateProfileImage(formData: FormData): Observable<ProfileImageResponse> {
    return this.http.post<ProfileImageResponse>(`${this.baseUrl}/avatar`, formData);
  }
}
