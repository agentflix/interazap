import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  OnInit,
  computed,
  inject,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { AuthService } from '@core/services/auth.service';
import {
  AfAlertComponent,
  AfLoadingButtonComponent,
  AfPasswordInputComponent,
  AfPasswordStrengthComponent,
  AfTextInputComponent,
} from '@shared/components';
import { AuthPageWrapperComponent } from '@layout/auth-layout/auth-page-wrapper.component';

/**
 * Página de redefinição de senha — exibe formulário de esqueci a senha ou de nova senha
 * conforme a presença do token na query string.
 */
@Component({
  selector: 'app-reset-password',
  standalone: true,
  imports: [
    RouterLink,
    ReactiveFormsModule,
    AfLoadingButtonComponent,
    AfTextInputComponent,
    AfPasswordStrengthComponent,
    AfPasswordInputComponent,
    AfAlertComponent,
    AuthPageWrapperComponent,
  ],
  templateUrl: './reset-password.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export default class ResetPasswordComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly authService = inject(AuthService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);

  readonly isSubmitting = signal(false);
  readonly successMessage = signal<string | null>(null);
  readonly errorMessage = signal<string | null>(null);

  readonly token = signal<string | null>(null);
  readonly emailFromParams = signal<string>('');

  readonly forgotForm = this.fb.group({
    email: this.fb.control('', [Validators.required, Validators.email]),
  });

  readonly resetForm = this.fb.group(
    {
      password: this.fb.nonNullable.control('', [Validators.required, Validators.minLength(8)]),
      password_confirmation: this.fb.nonNullable.control('', [Validators.required]),
    },
    { validators: this.passwordsMatchValidator },
  );

  readonly emailErrorMessage = computed(() => {
    const errors = this.forgotForm.controls.email.errors;
    if (errors?.['required']) return 'E-mail é obrigatório.';
    if (errors?.['email']) return 'E-mail inválido.';
    return 'Campo inválido.';
  });

  readonly confirmErrorMessage = computed(() => {
    const errors = this.resetForm.controls.password_confirmation.errors;
    if (errors?.['required']) return 'Confirmação é obrigatória.';
    if (this.resetForm.errors?.['passwordsMismatch']) return 'As senhas não coincidem.';
    return 'Campo inválido.';
  });

  /** Lê o token e e-mail da query string para determinar o modo da página (esqueci vs. redefinir). */
  ngOnInit(): void {
    const token = this.route.snapshot.queryParamMap.get('token');
    const email = this.route.snapshot.queryParamMap.get('email') ?? '';
    this.token.set(token);
    this.emailFromParams.set(email);
  }

  /** Envia o e-mail para receber o link de redefinição de senha. */
  submitForgot(): void {
    if (this.forgotForm.invalid || this.isSubmitting()) {
      this.forgotForm.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);
    this.successMessage.set(null);
    this.errorMessage.set(null);

    const email = this.forgotForm.getRawValue().email ?? '';

    this.authService
      .forgotPassword(email)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isSubmitting.set(false);
          this.successMessage.set('Link de redefinição enviado com sucesso.');
          this.forgotForm.reset({ email: '' });
        },
        error: () => {
          this.isSubmitting.set(false);
          this.errorMessage.set('Não foi possível enviar o link de redefinição.');
        },
      });
  }

  /** Envia a nova senha junto com o token para concluir a redefinição. */
  submitReset(): void {
    if (this.resetForm.invalid || this.isSubmitting()) {
      this.resetForm.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);
    this.successMessage.set(null);
    this.errorMessage.set(null);

    const { password, password_confirmation } = this.resetForm.getRawValue();

    this.authService
      .resetPassword({
        token: this.token() ?? '',
        email: this.emailFromParams(),
        password,
        password_confirmation,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isSubmitting.set(false);
          this.successMessage.set('Senha redefinida com sucesso!');
          setTimeout(() => this.router.navigate(['/auth/login']), 2000);
        },
        error: () => {
          this.isSubmitting.set(false);
          this.errorMessage.set('Link inválido ou expirado. Solicite um novo link de redefinição.');
        },
      });
  }

  private passwordsMatchValidator(group: import('@angular/forms').AbstractControl) {
    const password = group.get('password')?.value;
    const confirmation = group.get('password_confirmation')?.value;
    return password === confirmation ? null : { passwordsMismatch: true };
  }
}
