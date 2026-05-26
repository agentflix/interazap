import {
  ChangeDetectionStrategy,
  Component,
  inject,
  signal,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { AuthService } from '@core/services/auth.service';
import { AuthStoreService } from '@core/services/auth-store.service';
import { GoogleLoginButtonComponent } from '@core/components/google-login-button/google-login-button';
import {
  AfAlertComponent,
  AfLoadingButtonComponent,
  AfPasswordInputComponent,
  AfTextInputComponent,
} from '@shared/components';
import { AuthPageWrapperComponent } from '@layout/auth-layout/auth-page-wrapper.component';

/**
 * Página pública de cadastro com email/senha + Google OAuth.
 * Cria tenant + user + plano trial em uma requisição.
 *
 * @selector app-signup-page
 * @route /signup
 */
@Component({
  selector: 'app-signup-page',
  standalone: true,
  imports: [
    RouterLink,
    ReactiveFormsModule,
    GoogleLoginButtonComponent,
    AfTextInputComponent,
    AfPasswordInputComponent,
    AfLoadingButtonComponent,
    AfAlertComponent,
    AuthPageWrapperComponent,
  ],
  templateUrl: './signup-page.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SignupPageComponent {
  private readonly fb = inject(FormBuilder);
  private readonly authService = inject(AuthService);
  private readonly authStore = inject(AuthStoreService);
  private readonly router = inject(Router);

  readonly isLoading = signal(false);
  readonly errorMessage = signal<string | null>(null);

  readonly form = this.fb.nonNullable.group({
    name: ['', [Validators.required, Validators.minLength(2), Validators.maxLength(255)]],
    email: ['', [Validators.required, Validators.email, Validators.maxLength(255)]],
    password: ['', [Validators.required, Validators.minLength(8), Validators.maxLength(255)]],
    accept_terms: [false, [Validators.requiredTrue]],
  });

  /** Valida o formulário e envia os dados de cadastro para criar tenant, usuário e plano trial. */
  submit(): void {
    if (this.form.invalid || this.isLoading()) return;

    this.errorMessage.set(null);
    this.isLoading.set(true);

    const { name, email, password, accept_terms } = this.form.getRawValue();

    this.authService.signup({ name, email, password, accept_terms }).pipe(
      finalize(() => this.isLoading.set(false)),
    ).subscribe({
      next: (response) => {
        const user = response.data?.user;
        if (user) {
          this.authStore.setAuth(
            { ...user, permissions: response.data?.permissions ?? [], tenant_plan: response.data?.tenant_plan ?? null },
            response.data?.token ?? '',
          );
        }
        void this.router.navigate(['/auth/complete-signup']);
      },
      error: (err: { status?: number; error?: { errors?: Record<string, string[]>; message?: string } }) => {
        if (err.status === 422) {
          const errors = err.error?.errors ?? {};
          const firstError = Object.values(errors)[0]?.[0] ?? err.error?.message ?? 'Dados inválidos.';
          this.errorMessage.set(firstError);
        } else if (err.status === 429) {
          this.errorMessage.set('Muitas tentativas. Aguarde alguns minutos e tente novamente.');
        } else {
          this.errorMessage.set('Erro ao criar conta. Tente novamente.');
        }
      },
    });
  }
}
