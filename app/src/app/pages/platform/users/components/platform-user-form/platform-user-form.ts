import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  effect,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { type PlatformUser, PlatformUserService } from '@core/services/platform-user.service';
import { ToastService } from '@core/services/toast.service';
import { applyServerErrors } from '@core/utils/form-server-errors.util';
import {
  AfPasswordInputComponent,
  AfPasswordStrengthComponent,
  AfSelectInputComponent,
  AfSwitchInputComponent,
  AfTextInputComponent,
  AfCheckboxGroupComponent,
  AfCheckboxInputComponent,
  type AfSelectOption,
  type AfCheckboxOption,
} from '@shared/components';

/**
 * Formulário de criação e edição de usuários da plataforma com gerenciamento de roles e senha.
 */
@Component({
  selector: 'app-platform-user-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfTextInputComponent,
    AfPasswordStrengthComponent,
    AfPasswordInputComponent,
    AfSelectInputComponent,
    AfCheckboxGroupComponent,
    AfSwitchInputComponent,
    AfCheckboxInputComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './platform-user-form.html',
})
export class PlatformUserFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly service = inject(PlatformUserService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly lastLoadedId = signal<string | null>(null);

  readonly user = input<PlatformUser | null>(null);
  readonly companyOptions = input<AfSelectOption[]>([]);
  readonly roleOptions = input<readonly AfCheckboxOption[]>([]);
  readonly fixedTenantId = input<string | undefined>(undefined);

  readonly saved = output<PlatformUser>();
  readonly cancelled = output<undefined>();

  readonly isSaving = signal(false);
  readonly isEditing = computed(() => !!this.user());
  readonly isPasswordConfirmationRequired = computed(
    () => !this.isEditing() || this.form.controls.password.value.trim().length > 0,
  );
  readonly passwordConfirmationErrorMessage = computed(() => {
    const control = this.form.controls.password_confirmation;

    if (control.touched && control.hasError('required')) {
      return 'Confirmação de senha é obrigatória.';
    }

    if (control.touched && control.hasError('mismatch')) {
      return 'As senhas precisam ser idênticas.';
    }

    return 'Campo inválido.';
  });

  readonly form = this.fb.group({
    name: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    email: this.fb.control('', {
      nonNullable: true,
      validators: [Validators.required, Validators.email],
    }),
    roles: this.fb.control<string[]>([], { nonNullable: true }),
    password: this.fb.control('', { nonNullable: true }),
    password_confirmation: this.fb.control('', { nonNullable: true }),
    company_id: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    is_active: this.fb.control(true, { nonNullable: true }),
    force_password_change: this.fb.control(false, { nonNullable: true }),
  });

  constructor() {
    effect(() => {
      const item = this.user();
      if (item) {
        if (this.lastLoadedId() === item.id) return;
        this.lastLoadedId.set(item.id);
        this.form.reset({
          name: item.name,
          email: item.email,
          roles: item.roles ?? [],
          password: '',
          password_confirmation: '',
          company_id: this.resolveCompanyId(item),
          is_active: item.is_active,
          force_password_change: item.force_password_change ?? false,
        });
        this.setPasswordValidators(false);
      } else {
        this.lastLoadedId.set(null);
        this.resetForm();
        this.setPasswordValidators(true);
      }

      // Apply fixed tenant if provided
      const fixedId = this.fixedTenantId();
      if (fixedId) {
        this.form.controls.company_id.setValue(fixedId);
        this.form.controls.company_id.disable();
      }
    });
  }

  submit(): void {
    if (this.form.invalid || this.isSaving()) {
      this.form.markAllAsTouched();
      return;
    }

    const formValue = this.form.getRawValue();
    const payload: Partial<PlatformUser> & { password?: string; password_confirmation?: string; force_password_change?: boolean } = {
      name: formValue.name.trim(),
      email: formValue.email.trim(),
      roles: formValue.roles,
      company_id: formValue.company_id,
      tenant_id: formValue.company_id,
      is_active: formValue.is_active,
      force_password_change: formValue.force_password_change,
    };

    const editing = this.user();
    const password = formValue.password.trim();
    const passwordConfirmation = formValue.password_confirmation.trim();

    if (!editing && !password) {
      return;
    }

    if (password && password !== passwordConfirmation) {
      this.form.controls.password_confirmation.setErrors({ mismatch: true });
      this.form.controls.password_confirmation.markAsTouched();

      return;
    }

    if (password) {
      payload.password = password;
      payload.password_confirmation = passwordConfirmation;
    }

    const request = editing
      ? this.service.update(editing.id, payload)
      : this.service.create(payload);

    this.isSaving.set(true);
    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        this.isSaving.set(false);
        this.saved.emit(response.data);
      },
      error: (err: { status?: number; error?: { message?: string; errors?: Record<string, string[]> } }) => {
        this.isSaving.set(false);
        if (err.status === 422 && err.error?.errors) {
          applyServerErrors(this.form, err.error.errors);
          return;
        }
        this.toast.error(err.error?.message ?? 'Erro ao salvar o usuário.');
      },
    });
  }

  cancel(): void {
    this.cancelled.emit(undefined);
  }

  private resolveCompanyId(user: PlatformUser): string {
    if (user.company_id !== undefined && user.company_id !== null) {
      return String(user.company_id);
    }

    if (user.tenant_id !== undefined && user.tenant_id !== null && user.tenant_id.trim() !== '') {
      return user.tenant_id;
    }

    return '';
  }

  private setPasswordValidators(isRequired: boolean): void {
    const passwordValidators = isRequired
      ? [Validators.required, Validators.minLength(8)]
      : [Validators.minLength(8)];
    const confirmationValidators = isRequired ? [Validators.required] : [];

    this.form.controls.password.setValidators(passwordValidators);
    this.form.controls.password_confirmation.setValidators(confirmationValidators);
    this.form.controls.password.updateValueAndValidity({ emitEvent: false });
    this.form.controls.password_confirmation.updateValueAndValidity({ emitEvent: false });
  }

  private resetForm(): void {
    this.form.reset({
      name: '',
      email: '',
      roles: [],
      password: '',
      password_confirmation: '',
      company_id: this.fixedTenantId() ?? '',
      is_active: true,
      force_password_change: false,
    });

    // Re-disable if fixed tenant is set
    if (this.fixedTenantId()) {
      this.form.controls.company_id.setValue(this.fixedTenantId()!);
      this.form.controls.company_id.disable();
    }
  }
}
