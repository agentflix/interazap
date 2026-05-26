import { Component, OnInit, inject } from '@angular/core';
import { Router, ActivatedRoute, RouterLink } from '@angular/router';
import { AuthService } from '@core/services/auth.service';
import { AuthStoreService } from '@core/services/auth-store.service';

const ERROR_MESSAGES: Record<string, string> = {
  not_registered: 'E-mail não cadastrado. Faça cadastro para continuar.',
  not_linked: 'Conta Google não vinculada. Acesse seu perfil para vincular.',
  already_registered: 'E-mail já cadastrado. Faça login para continuar.',
  already_linked: 'Esta conta Google já está vinculada a outro usuário.',
  oauth_failed: 'Falha ao autenticar com Google. Tente novamente.',
};

/**
 * Página técnica que processa o callback OAuth do Google.
 * Lê `?token=`, `?error=` e `?linked=` da query string e age de acordo:
 * - `linked=true` → exibe confirmação e redireciona para /settings/profile
 * - `token` presente → persiste e redireciona para /dashboard
 * - `error` presente → exibe mensagem de erro traduzida
 */
@Component({
  selector: 'app-google-callback-page',
  standalone: true,
  imports: [RouterLink],
  templateUrl: './google-callback-page.html',
})
export class GoogleCallbackPageComponent implements OnInit {
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly authService = inject(AuthService);
  private readonly authStore = inject(AuthStoreService);

  isLoading = true;
  hasError = false;
  errorMessage = '';
  successMessage = '';

  ngOnInit(): void {
    const params = this.route.snapshot.queryParamMap;
    const error = params.get('error');
    const linked = params.get('linked');
    const token = params.get('token');

    if (error !== null) {
      this.isLoading = false;
      this.hasError = true;
      this.errorMessage =
        ERROR_MESSAGES[error] ?? 'Falha ao autenticar com Google. Tente novamente.';
      return;
    }

    if (linked === 'true') {
      this.isLoading = false;
      this.successMessage = 'Conta Google vinculada com sucesso!';
      void this.router.navigate(['/settings/profile']);
      return;
    }

    if (!token) {
      this.isLoading = false;
      this.hasError = true;
      this.errorMessage = 'Token de autenticação não encontrado. Tente novamente.';
      return;
    }

    this.authService.handleGoogleToken(token).subscribe({
      next: (response) => {
        const user = response.data?.user;
        if (user) {
          this.authStore.setAuth(
            { ...user, permissions: response.data?.permissions ?? [], tenant_plan: response.data?.tenant_plan ?? null },
            token,
          );
        }
        const target = user?.has_password === false ? '/auth/complete-signup' : '/dashboard';
        void this.router.navigate([target]);
      },
      error: (err: unknown) => {
        this.isLoading = false;
        this.hasError = true;
        this.errorMessage = 'Falha ao autenticar com Google. Tente novamente.';
        console.error('Google OAuth callback error:', err);
      },
    });
  }
}
