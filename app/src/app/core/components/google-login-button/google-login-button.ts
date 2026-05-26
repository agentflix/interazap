import { Component } from '@angular/core';
import { environment } from '@env/environment';

/**
 * Botão de login/cadastro com Google OAuth.
 * Ao clicar, redireciona o usuário para o endpoint de redirecionamento Google no backend.
 *
 * @example
 * ```html
 * <app-google-login-button />
 * ```
 */
@Component({
  selector: 'app-google-login-button',
  standalone: true,
  imports: [],
  templateUrl: './google-login-button.html',
  styleUrl: './google-login-button.scss',
})
export class GoogleLoginButtonComponent {
  /** Redireciona o usuário para o fluxo OAuth do Google via backend. */
  redirectToGoogle(): void {
    window.location.href = `${environment.apiUrl}/auth/google/redirect`;
  }
}
