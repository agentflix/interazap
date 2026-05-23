import { ChangeDetectionStrategy, Component, computed, inject, signal } from '@angular/core';
import { finalize } from 'rxjs';
import {
  type FormControl,
  type FormGroup,
  FormBuilder,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { Router } from '@angular/router';
import {
  AfFormErrorComponent,
  AfLoadingButtonComponent,
  AfPasswordInputComponent,
  AfPasswordStrengthComponent,
} from '@shared/components';
import { AuthPageWrapperComponent } from '@layout/auth-layout/auth-page-wrapper.component';
import { AuthStoreService } from '@core/services/auth-store.service';
import { ProfileService } from '@core/services/profile.service';
import { ToastService } from '@core/services/toast.service';

/**
 * Force password change page — users with force_password_change flag
 * are redirected here and cannot navigate away until they set a new password.
 *
 * @selector app-force-change-password
 */
@Component({
  selector: 'app-force-change-password',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfFormErrorComponent,
    AfLoadingButtonComponent,
    AfPasswordStrengthComponent,
    AfPasswordInputComponent,
    AuthPageWrapperComponent,
  ],
  templateUrl: './force-change-password.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForceChangePassword {
  private readonly fb = inject(FormBuilder);
  private readonly profileService = inject(ProfileService);
  private readonly authStore = inject(AuthStoreService);
  private readonly router = inject(Router);
  private readonly toast = inject(ToastService);

  readonly isSaving = signal(false);
  readonly submitted = signal(false);

  readonly form: FormGroup<{
    password: FormControl<string>;
    password_confirmation: FormControl<string>;
  }> = this.fb.group({
    password: this.fb.control('', {
      nonNullable: true,
      validators: [Validators.required, Validators.minLength(8)],
    }),
    password_confirmation: this.fb.control('', {
      nonNullable: true,
      validators: [Validators.required],
    }),
  });

  readonly passwordsMismatch = computed(() => {
    const password = this.form.controls.password.value;
    const confirmation = this.form.controls.password_confirmation.value;
    return Boolean(password && confirmation && password !== confirmation);
  });

  readonly passwordConfirmationErrorMessage = computed(() => {
    const errors = this.form.controls.password_confirmation.errors;

    if (errors?.['required']) {
      return 'Confirmação de senha é obrigatória.';
    }

    return 'Campo inválido.';
  });

  submit(): void {
    if (this.form.invalid || this.passwordsMismatch() || this.isSaving()) {
      this.form.markAllAsTouched();
      return;
    }

    const { password, password_confirmation } = this.form.getRawValue();

    this.isSaving.set(true);

    this.profileService
      .forcePasswordChange({ password: password ?? '', password_confirmation: password_confirmation ?? '' })
      .pipe(finalize(() => this.isSaving.set(false)))
      .subscribe({
        next: () => {
          this.submitted.set(true);
          this.toast.success('Senha redefinida com sucesso. Faça login novamente.');
          this.authStore.logout();
          void this.router.navigate(['/auth/login']);
        },
        error: (err) => {
          const msg = err?.error?.message || 'Erro ao redefinir senha. Tente novamente.';
          this.toast.error(msg);
        },
      });
  }
}
