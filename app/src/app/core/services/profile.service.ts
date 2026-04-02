import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';

export interface ProfileUser {
  id: string | number;
  name: string;
  email: string;
  avatar_url?: string | null;
  two_factor_enabled?: boolean;
}

export interface ProfileResponse {
  data: ProfileUser;
}

export interface UpdateProfilePayload {
  name: string;
}

export interface UpdatePasswordPayload {
  current_password: string;
  password: string;
  password_confirmation: string;
}

export interface ProfileImageResponse {
  data: {
    avatar_url?: string | null;
  };
}

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
