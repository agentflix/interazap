import { ChangeDetectionStrategy, Component, computed, inject } from '@angular/core';
import {
  type FormControl,
  type FormGroup,
  FormBuilder,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { RouterLink } from '@angular/router';
import {
  AfButtonComponent,
  AfCheckboxInputComponent,
  AfFormErrorComponent,
  AfPasswordInputComponent,
  AfPasswordStrengthComponent,
} from '@shared/components';
import { AuthPageWrapperComponent } from '@layout/auth-layout/auth-page-wrapper.component';

/**
 * Página de criação de senha para novos usuários do módulo de autenticação.
 */
@Component({
  selector: 'app-create-password',
  standalone: true,
  imports: [
    RouterLink,
    ReactiveFormsModule,
    AfButtonComponent,
    AfPasswordStrengthComponent,
    AfPasswordInputComponent,
    AfCheckboxInputComponent,
    AfFormErrorComponent,
    AuthPageWrapperComponent,
  ],
  templateUrl: './create-password.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export default class CreatePasswordComponent {
  private readonly fb = inject(FormBuilder);

  readonly form: FormGroup<{
    password: FormControl<string>;
    passwordConfirmation: FormControl<string>;
    remember: FormControl<boolean>;
  }> = this.fb.group({
    password: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    passwordConfirmation: this.fb.control('', {
      nonNullable: true,
      validators: [Validators.required],
    }),
    remember: this.fb.control(false, { nonNullable: true }),
  });

  readonly passwordsMismatch = computed(() => {
    const password = this.form.controls.password.value;
    const confirmation = this.form.controls.passwordConfirmation.value;
    return Boolean(password && confirmation && password !== confirmation);
  });

  readonly passwordConfirmationErrorMessage = computed(() => {
    const errors = this.form.controls.passwordConfirmation.errors;

    if (errors?.['required']) {
      return 'Confirmação de senha é obrigatória.';
    }

    return 'Campo inválido.';
  });

  /** Envia o formulário de criação de senha após validação local. */
  submit(): void {
    if (this.form.invalid || this.passwordsMismatch()) {
      this.form.markAllAsTouched();
      return;
    }
  }
}
