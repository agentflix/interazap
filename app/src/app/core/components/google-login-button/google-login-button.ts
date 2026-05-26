import { Component, Input } from '@angular/core';
import { environment } from '@env/environment';

/**
 * Botão de login/cadastro/vinculação com Google OAuth.
 * Ao clicar, redireciona o usuário para o endpoint de redirecionamento Google no backend,
 * passando o `intent` como query param para que o backend diferencie o fluxo.
 *
 * @example
 * ```html
 * <app-google-login-button intent="login" />
 * <app-google-login-button intent="signup" />
 * <app-google-login-button intent="link" />
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
  @Input() intent: 'login' | 'signup' | 'link' = 'login';

  redirectToGoogle(): void {
    window.location.href = `${environment.apiUrl}/auth/google/redirect?intent=${this.intent}`;
  }
}
