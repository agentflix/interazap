import { Component, OnInit, inject } from '@angular/core';
import { Router, ActivatedRoute } from '@angular/router';
import { AuthService } from '@core/services/auth.service';

/**
 * Página técnica que processa o callback OAuth do Google.
 * Lê o token `?token=...` da query string, persiste-o e
 * redireciona para o dashboard. Exibe loading durante o processo.
 */
@Component({
  selector: 'app-google-callback-page',
  standalone: true,
  imports: [],
  templateUrl: './google-callback-page.html',
})
export class GoogleCallbackPageComponent implements OnInit {
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly authService = inject(AuthService);

  isLoading = true;
  hasError = false;
  errorMessage = '';

  ngOnInit(): void {
    const token = this.route.snapshot.queryParamMap.get('token');

    if (!token) {
      this.isLoading = false;
      this.hasError = true;
      this.errorMessage = 'Token de autenticação não encontrado. Tente novamente.';
      return;
    }

    this.authService.handleGoogleToken(token).subscribe({
      next: () => {
        this.router.navigate(['/dashboard']);
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
