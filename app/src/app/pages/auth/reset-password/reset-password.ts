import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { RouterLink } from '@angular/router';
import { AuthService } from '@core/services/auth.service';
import {
  AfAlertComponent,
  AfLoadingButtonComponent,
  AfTextInputComponent,
} from '@shared/components';
import { AuthPageWrapperComponent } from '@layout/auth-layout/auth-page-wrapper.component';

/**
 * Reset password page component for the Auth module.
 * @selector app-reset-password
 */
@Component({
  selector: 'app-reset-password',
  standalone: true,
  imports: [
    RouterLink,
    ReactiveFormsModule,
    AfLoadingButtonComponent,
    AfTextInputComponent,
    AfAlertComponent,
    AuthPageWrapperComponent,
  ],
  templateUrl: './reset-password.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export default class ResetPasswordComponent {
  private readonly fb = inject(FormBuilder);
  private readonly authService = inject(AuthService);
  private readonly destroyRef = inject(DestroyRef);

  readonly isSubmitting = signal(false);
  readonly successMessage = signal<string | null>(null);
  readonly errorMessage = signal<string | null>(null);

  readonly form = this.fb.group({
    email: this.fb.control('', [Validators.required, Validators.email]),
  });

  readonly emailErrorMessage = computed(() => {
    const errors = this.form.controls.email.errors;

    if (errors?.['required']) {
      return 'E-mail é obrigatório.';
    }

    if (errors?.['email']) {
      return 'E-mail inválido.';
    }

    return 'Campo inválido.';
  });

  submit(): void {
    if (this.form.invalid || this.isSubmitting()) {
      this.form.markAllAsTouched();
      return;
    }

    this.isSubmitting.set(true);
    this.successMessage.set(null);
    this.errorMessage.set(null);
    const email = this.form.getRawValue().email ?? '';

    this.authService
      .forgotPassword(email)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isSubmitting.set(false);
          this.successMessage.set('Link de redefinição enviado com sucesso.');
          this.form.reset({ email: '' });
        },
        error: () => {
          this.isSubmitting.set(false);
          this.errorMessage.set('Não foi possível enviar o link de redefinição.');
        },
      });
  }
}
