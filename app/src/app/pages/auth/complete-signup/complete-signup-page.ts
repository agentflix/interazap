import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  effect,
  inject,
  signal,
  computed,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { AuthService } from '@core/services/auth.service';
import { AuthStoreService } from '@core/services/auth-store.service';
import {
  AfAlertComponent,
  AfLoadingButtonComponent,
  AfPasswordInputComponent,
  AfTextInputComponent,
} from '@shared/components';
import { AuthPageWrapperComponent } from '@layout/auth-layout/auth-page-wrapper.component';

@Component({
  selector: 'app-complete-signup-page',
  standalone: true,
  imports: [
    RouterLink,
    ReactiveFormsModule,
    AfTextInputComponent,
    AfPasswordInputComponent,
    AfLoadingButtonComponent,
    AfAlertComponent,
    AuthPageWrapperComponent,
  ],
  templateUrl: './complete-signup-page.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CompleteSignupPageComponent {
  private readonly fb = inject(FormBuilder);
  private readonly authService = inject(AuthService);
  private readonly authStore = inject(AuthStoreService);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  readonly isLoading = signal(false);
  readonly errorMessage = signal<string | null>(null);

  readonly needsPassword = computed(() => this.authStore.user()?.has_password === false);

  readonly form = this.fb.nonNullable.group({
    company_name: this.fb.nonNullable.control('', [Validators.required, Validators.minLength(2), Validators.maxLength(255)]),
    phone: this.fb.nonNullable.control('', [Validators.required, Validators.maxLength(30)]),
    password: this.fb.nonNullable.control('', [Validators.minLength(8), Validators.maxLength(255)]),
  });

  constructor() {
    effect(() => {
      const ctrl = this.form.controls.password;
      if (this.needsPassword()) {
        ctrl.addValidators(Validators.required);
      } else {
        ctrl.removeValidators(Validators.required);
      }
      ctrl.updateValueAndValidity();
    });
  }

  protected fieldError(field: 'company_name' | 'phone' | 'password'): string {
    const c = this.form.controls[field];
    if (!c.touched || !c.errors) return '';
    if (c.errors['required']) return 'Campo obrigatório.';
    if (c.errors['minlength']) {
      const min = c.errors['minlength'].requiredLength as number;
      return field === 'password'
        ? `A senha deve ter no mínimo ${min} caracteres.`
        : `Mínimo ${min} caracteres.`;
    }
    return '';
  }

  protected submit(): void {
    if (this.form.invalid || this.isLoading()) {
      this.form.markAllAsTouched();
      return;
    }

    this.isLoading.set(true);
    this.errorMessage.set(null);

    const { company_name, phone, password } = this.form.getRawValue();
    const payload: { company_name: string; phone: string; password?: string } = { company_name, phone };
    if (this.needsPassword() && password) {
      payload.password = password;
    }

    this.authService
      .completeSignup(payload)
      .pipe(
        takeUntilDestroyed(this.destroyRef),
        finalize(() => this.isLoading.set(false)),
      )
      .subscribe({
        next: (response) => {
          this.authStore.updateUser(response.data);
          void this.router.navigate(['/dashboard']);
        },
        error: (err: { error?: { message?: string } }) => {
          this.errorMessage.set(err.error?.message ?? 'Não foi possível concluir o cadastro.');
        },
      });
  }
}
