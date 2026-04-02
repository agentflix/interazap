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
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import {
  AfAlertComponent,
  AfCheckboxGroupComponent,
  AfPasswordInputComponent,
  AfPasswordStrengthComponent,
  AfSwitchInputComponent,
  AfTextInputComponent,
  type AfCheckboxOption,
} from '@shared/components';
import {
  type CreateSettingsUserPayload,
  type SettingsUser,
  type UpdateSettingsUserPayload,
  SettingsUsersService,
} from '@core/services/settings-users.service';
import { AuthStoreService } from '@core/services/auth-store.service';
import { RoleService } from '@core/services/role.service';
import { type Role } from '@core/models/role.model';

/**
 * Settings user form — create/edit a tenant user inside a modal.
 * Business logic preserved verbatim from source. Visual layer migrated to UI Kit.
 */
@Component({
  selector: 'app-settings-user-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfTextInputComponent,
    AfCheckboxGroupComponent,
    AfPasswordStrengthComponent,
    AfPasswordInputComponent,
    AfSwitchInputComponent,
    AfAlertComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './settings-user-form.html',
})
export class SettingsUserFormComponent {
  private readonly fb = inject(NonNullableFormBuilder);
  private readonly service = inject(SettingsUsersService);
  private readonly authStore = inject(AuthStoreService);
  private readonly roleService = inject(RoleService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly lastLoadedId = signal<string | null>(null);

  /** @input User to edit; null for create mode */
  readonly user = input<SettingsUser | null>(null);
  readonly saved = output<SettingsUser>();
  readonly cancelled = output<void>();

  readonly isSaving = signal(false);
  readonly isEditing = computed(() => !!this.user());
  readonly errorMessage = signal('');
  readonly availableRoles = signal<Role[]>([]);
  readonly roleOptions = computed<readonly AfCheckboxOption[]>(() =>
    this.availableRoles().map((role) => ({ label: role.name, value: role.name })),
  );
  readonly isPasswordConfirmationRequired = computed(
    () => !this.isEditing() || this.form.controls.password.value.trim().length > 0,
  );
  readonly passwordConfirmationErrorMessage = computed(() => {
    const control = this.form.controls.password_confirmation;

    if (control.touched && control.errors?.['required']) {
      return 'Confirmação de senha é obrigatória.';
    }

    if (control.touched && control.errors?.['mismatch']) {
      return 'As senhas precisam ser idênticas.';
    }

    return 'Campo inválido.';
  });

  readonly form = this.fb.group({
    name: this.fb.control('', Validators.required),
    email: this.fb.control('', [Validators.required, Validators.email]),
    roles: this.fb.control<string[]>([]),
    password: this.fb.control(''),
    password_confirmation: this.fb.control(''),
    is_active: this.fb.control(true),
  });

  constructor() {
    this.loadRoles();

    effect(() => {
      const item = this.user();
      if (!item) {
        this.lastLoadedId.set(null);
        this.resetForm();
        this.form.controls.email.enable();
        this.setPasswordValidators(true);
        return;
      }

      if (this.lastLoadedId() === item.id) return;
      this.lastLoadedId.set(item.id);
      this.form.reset({
        name: item.name,
        email: item.email,
        roles: item.roles ?? [],
        password: '',
        password_confirmation: '',
        is_active: item.is_active,
      });
      this.form.controls.email.disable();
      this.setPasswordValidators(false);
    });
  }

  submit(): void {
    if (this.form.invalid || this.isSaving()) {
      this.form.markAllAsTouched();
      return;
    }

    const currentUser = this.authStore.user();
    if (!currentUser?.tenant_id) {
      this.errorMessage.set('Não foi possível identificar o inquilino do usuário logado.');
      return;
    }

    const formValue = this.form.getRawValue();
    const password = formValue.password.trim();
    const passwordConfirmation = formValue.password_confirmation.trim();

    if (password && password !== passwordConfirmation) {
      this.form.controls.password_confirmation.setErrors({ mismatch: true });
      this.form.controls.password_confirmation.markAsTouched();
      return;
    }

    const basePayload = {
      name: formValue.name.trim(),
      email: formValue.email.trim(),
      roles: formValue.roles,
      is_active: formValue.is_active ?? true,
      tenant_id: String(currentUser.tenant_id),
    };

    const editing = this.user();
    this.isSaving.set(true);
    this.errorMessage.set('');

    if (!editing) {
      if (!password) {
        this.isSaving.set(false);
        this.errorMessage.set('Senha é obrigatória.');
        return;
      }

      const payload: CreateSettingsUserPayload = {
        ...basePayload,
        password,
        password_confirmation: passwordConfirmation,
      };
      this.service
        .create(payload)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe({
          next: (response) => {
            this.isSaving.set(false);
            this.saved.emit(response.data.user);
          },
          error: (error) => {
            this.isSaving.set(false);
            this.errorMessage.set(error.error?.message || 'Não foi possível criar o usuário.');
          },
        });
      return;
    }

    const updatePayload: UpdateSettingsUserPayload = { ...basePayload };
    if (password) {
      updatePayload.password = password;
      updatePayload.password_confirmation = passwordConfirmation;
    }

    this.service
      .update(editing.id, updatePayload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.isSaving.set(false);
          this.saved.emit(response.data.user);
        },
        error: (error) => {
          this.isSaving.set(false);
          this.errorMessage.set(error.error?.message || 'Não foi possível atualizar o usuário.');
        },
      });
  }

  cancel(): void {
    this.cancelled.emit();
  }

  // ── Error getters ─────────────────────────────────────────────────────────────

  nameError(): string {
    const c = this.form.controls.name;
    if (!c.touched || !c.errors) return '';
    if (c.errors['required']) return 'Nome é obrigatório.';
    return '';
  }

  emailError(): string {
    const c = this.form.controls.email;
    if (!c.touched || !c.errors) return '';
    if (c.errors['required']) return 'E-mail é obrigatório.';
    if (c.errors['email']) return 'E-mail inválido.';
    return '';
  }

  passwordError(): string {
    const c = this.form.controls.password;
    if (!c.touched || !c.errors) return '';
    if (c.errors['required']) return 'Senha é obrigatória.';
    if (c.errors['minlength']) return 'Senha deve ter no mínimo 8 caracteres.';
    return '';
  }

  private resetForm(): void {
    this.form.reset({
      name: '',
      email: '',
      roles: [],
      password: '',
      password_confirmation: '',
      is_active: true,
    });
    this.errorMessage.set('');
  }

  private setPasswordValidators(isCreate: boolean): void {
    const passwordValidators = isCreate
      ? [Validators.required, Validators.minLength(8)]
      : [Validators.minLength(8)];
    const confirmationValidators = isCreate ? [Validators.required] : [];

    this.form.controls.password.setValidators(passwordValidators);
    this.form.controls.password_confirmation.setValidators(confirmationValidators);
    this.form.controls.password.updateValueAndValidity({ emitEvent: false });
    this.form.controls.password_confirmation.updateValueAndValidity({ emitEvent: false });
  }

  private loadRoles(): void {
    this.roleService
      .listAll()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => this.availableRoles.set(response.data),
        error: () => this.errorMessage.set('Não foi possível carregar os perfis.'),
      });
  }
}
