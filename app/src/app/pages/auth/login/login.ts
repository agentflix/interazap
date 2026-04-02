import {
  type OnInit,
  computed,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  effect,
  inject,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import {
  type FormControl,
  type FormGroup,
  FormBuilder,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { finalize } from 'rxjs';
import { type AuthResponse, AuthService } from '@core/services/auth.service';
import { AuthStoreService } from '@core/services/auth-store.service';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfCheckboxInputComponent,
  AfLoadingButtonComponent,
  AfOtpInputComponent,
  AfPasswordInputComponent,
  AfTextInputComponent,
} from '@shared/components';
import { AuthPageWrapperComponent } from '@layout/auth-layout/auth-page-wrapper.component';

/**
 * Login page component for the Auth module.
 * @selector app-login
 */
@Component({
  selector: 'app-login',
  standalone: true,
  imports: [
    RouterLink,
    ReactiveFormsModule,
    AfTextInputComponent,
    AfPasswordInputComponent,
    AfCheckboxInputComponent,
    AfOtpInputComponent,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfAlertComponent,
    AuthPageWrapperComponent,
  ],
  templateUrl: './login.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export default class LoginComponent implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly authService = inject(AuthService);
  private readonly authStore = inject(AuthStoreService);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  readonly isLoading = signal(false);
  readonly message = signal<string | null>(null);
  readonly twoFactorPendingEmail = signal<string | null>(null);
  readonly twoFactorCode = signal('');
  readonly hasTwoFactorChallenge = computed(
    () => this.twoFactorPendingEmail() !== null && this.twoFactorPendingEmail() !== '',
  );

  readonly form: FormGroup<{
    email: FormControl<string>;
    password: FormControl<string>;
    remember: FormControl<boolean>;
  }>;

  readonly emailErrorMessage = computed(() => {
    const errors = this.form.controls.email.errors;

    if ((errors?.['required'] as boolean | undefined) === true) {
      return 'E-mail é obrigatório.';
    }

    if ((errors?.['email'] as boolean | undefined) === true) {
      return 'E-mail inválido.';
    }

    return 'Campo inválido.';
  });

  readonly passwordErrorMessage = computed(() => {
    const errors = this.form.controls.password.errors;

    if ((errors?.['required'] as boolean | undefined) === true) {
      return 'Senha é obrigatória.';
    }

    if (
      (errors?.['minlength'] as { requiredLength: number; actualLength: number } | undefined) !==
      undefined
    ) {
      return 'Senha deve ter no mínimo 6 caracteres.';
    }

    return 'Campo inválido.';
  });

  constructor() {
    this.form = this.fb.group({
      email: this.fb.control('', {
        nonNullable: true,
        validators: [Validators.required, Validators.email],
      }),
      password: this.fb.control('', {
        nonNullable: true,
        validators: [Validators.required, Validators.minLength(6)],
      }),
      remember: this.fb.control(false, {
        nonNullable: true,
      }),
    });

    effect(() => {
      if (this.isLoading()) {
        this.form.disable({ emitEvent: false });
      } else {
        this.form.enable({ emitEvent: false });
      }
    });
  }

  ngOnInit(): void {
    if (this.authStore.isAuthenticated()) {
      void this.router.navigate(['/dashboard']);
    }
  }

  submit(): void {
    if (this.hasTwoFactorChallenge()) {
      this.submitTwoFactor();
      return;
    }

    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    const { email, password } = this.form.getRawValue();
    this.isLoading.set(true);
    this.message.set(null);

    this.authService
      .login({ email: email ?? '', password: password ?? '' })
      .pipe(
        finalize(() => this.isLoading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (response) => {
          const requiresTwoFactor =
            response.data?.requires_2fa === true || response.data?.two_factor_required === true;

          if (requiresTwoFactor) {
            this.twoFactorPendingEmail.set(response.data?.email ?? email ?? '');
            this.twoFactorCode.set('');
            this.message.set(null);
            return;
          }

          this.completeAuthentication(response);
        },
        error: () => {
          this.message.set('Credenciais invalidas.');
        },
      });
  }

  isSubmitDisabled(): boolean {
    if (this.hasTwoFactorChallenge()) {
      return this.twoFactorCode().length !== 6;
    }

    return this.form.invalid;
  }

  onTwoFactorCodeCompleted(code: string): void {
    this.twoFactorCode.set(code);
    this.message.set(null);
  }

  cancelTwoFactorChallenge(): void {
    this.twoFactorPendingEmail.set(null);
    this.twoFactorCode.set('');
    this.message.set(null);
  }

  private submitTwoFactor(): void {
    const email = this.twoFactorPendingEmail();
    const code = this.twoFactorCode();

    if (email === null || email === '' || code.length !== 6) {
      this.message.set('Informe o código de verificação com 6 dígitos.');
      return;
    }

    this.isLoading.set(true);
    this.message.set(null);

    this.authService
      .loginWith2FA(email, code)
      .pipe(
        finalize(() => this.isLoading.set(false)),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe({
        next: (response) => {
          this.completeAuthentication(response);
        },
        error: () => {
          this.message.set('Código de verificação inválido.');
        },
      });
  }

  private completeAuthentication(response: AuthResponse): void {
    const user = response.data?.user;
    const token = response.data?.token;

    if (user === undefined || token === undefined || token === '') {
      this.message.set('Não foi possível concluir o login.');
      return;
    }

    this.authStore.setAuth(
      {
        ...user,
        permissions: response.data?.permissions ?? [],
        tenant_plan: response.data?.tenant_plan ?? null,
      },
      token,
    );
    this.twoFactorPendingEmail.set(null);
    this.twoFactorCode.set('');
    void this.router.navigate(['/dashboard']);
  }
}
