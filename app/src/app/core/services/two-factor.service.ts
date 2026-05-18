import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { type Observable } from 'rxjs';
import { environment } from '@env/environment';
import type { TwoFactorRegenerateResponse, TwoFactorSetupResponse, TwoFactorStatusResponse, TwoFactorValidateResponse } from '@core/models/two-factor.model';
export type { TwoFactorRegenerateResponse, TwoFactorSetupResponse, TwoFactorStatusResponse, TwoFactorValidateResponse } from '@core/models/two-factor.model';



/**
 * Serviço para gerenciamento de autenticação de dois fatores (2FA).
 */
@Injectable({ providedIn: 'root' })
export class TwoFactorService {
  private readonly baseUrl = `${environment.apiUrl}/auth/2fa`;
  private readonly http = inject(HttpClient);

  /** Consultar status atual do 2FA. */
  getStatus(): Observable<TwoFactorStatusResponse> {
    return this.http.get<TwoFactorStatusResponse>(`${this.baseUrl}/status`);
  }

  /** Iniciar configuração do 2FA (retorna secret e QR Code). */
  setup(): Observable<TwoFactorSetupResponse> {
    return this.http.post<TwoFactorSetupResponse>(`${this.baseUrl}/setup`, {});
  }

  /** Validar código TOTP e retornar códigos de recuperação. */
  validate(code: string): Observable<TwoFactorValidateResponse> {
    return this.http.post<TwoFactorValidateResponse>(`${this.baseUrl}/validate`, { code });
  }

  /** Desativar o 2FA. */
  disable(password: string): Observable<null> {
    return this.http.post<null>(`${this.baseUrl}/disable`, { password });
  }

  /** Regenerar códigos de recuperação. */
  regenerateRecoveryCodes(password: string): Observable<TwoFactorRegenerateResponse> {
    return this.http.post<TwoFactorRegenerateResponse>(`${this.baseUrl}/recovery-codes`, {
      password,
    });
  }
}
