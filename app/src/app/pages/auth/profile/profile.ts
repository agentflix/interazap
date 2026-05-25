import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import {
  type AbstractControl,
  type ValidationErrors,
  FormBuilder,
  FormControl,
  ReactiveFormsModule,
  Validators,
} from '@angular/forms';
import { RouterLink } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfAvatarComponent,
  AfButtonComponent,
  AfEmptyStateComponent,
  AfFileInputComponent,
  AfLoadingButtonComponent,
  AfPageTitleComponent,
  AfPasswordInputComponent,
  AfSkeletonComponent,
  AfStatusBadgeComponent,
  AfTextInputComponent,
} from '@shared/components';
import {
  type UpdatePasswordPayload,
  type UpdateProfilePayload,
  ProfileService,
} from '@core/services/profile.service';
import { TwoFactorService } from '@core/services/two-factor.service';
import { AuthStoreService } from '@core/services/auth-store.service';
import { ToastService } from '@core/services/toast.service';
import { getInitials } from '@shared/utils/string.utils';

/**
 * Profile page — personal info, password change, avatar upload, 2FA status.
 * Business logic preserved verbatim from source. Visual layer migrated to UI Kit.
 */
@Component({
  selector: 'app-profile',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    RouterLink,
    LucideAngularModule,
    AfButtonComponent,
    AfPageTitleComponent,
    AfTextInputComponent,
    AfPasswordInputComponent,
    AfAvatarComponent,
    AfFileInputComponent,
    AfSkeletonComponent,
    AfEmptyStateComponent,
    AfLoadingButtonComponent,
    AfAlertComponent,
    AfStatusBadgeComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './profile.html',
})
export class Profile implements OnInit {
  private readonly fb = inject(FormBuilder);
  private readonly profileService = inject(ProfileService);
  private readonly twoFactorService = inject(TwoFactorService);
  private readonly authStore = inject(AuthStoreService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  // ── Profile form ─────────────────────────────────────────────────────────────
  readonly profileForm = this.fb.group({
    name: this.fb.control('', [Validators.required]),
    email: this.fb.control({ value: '', disabled: true }),
  });

  // ── Password form ─────────────────────────────────────────────────────────────
  readonly passwordForm = this.fb.group(
    {
      current_password: this.fb.nonNullable.control('', Validators.required),
      password: this.fb.nonNullable.control('', [Validators.required, Validators.minLength(8)]),
      password_confirmation: this.fb.nonNullable.control('', Validators.required),
    },
    { validators: matchPasswords },
  );

  // ── Loading states ─────────────────────────────────────────────────────────────
  readonly isLoading = signal(false);
  readonly hasLoadError = signal(false);
  readonly isUpdatingProfile = signal(false);
  readonly isUpdatingPassword = signal(false);
  readonly isUploadingAvatar = signal(false);

  // ── Feedback signals ────────────────────────────────────────────────────────
  readonly profileErrorMessage = signal('');
  readonly passwordErrorMessage = signal('');
  readonly avatarError = signal('');

  // ── User state ───────────────────────────────────────────────────────────────
  readonly avatarUrl = signal<string | null>(null);
  readonly twoFactorEnabled = signal(false);
  readonly avatarFileControl = new FormControl<FileList | null>(null);
  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasLoadError() && !this.profileForm.controls.name.value,
  );

  readonly displayName = computed(() => this.authStore.user()?.name ?? '');
  readonly initials = computed(() => this.getInitials(this.displayName()));

  ngOnInit(): void {
    this.avatarFileControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((files) => {
        const file = files?.item(0);
        if (file) this.uploadAvatarFile(file);
      });
    this.loadProfile();
  }

  // ── Data loading ─────────────────────────────────────────────────────────────

  loadProfile(): void {
    this.isLoading.set(true);
    this.hasLoadError.set(false);
    this.profileService
      .getProfile()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const user = response.data;
          this.profileForm.reset({
            name: user.name,
            email: user.email,
          });
          this.avatarUrl.set(user.avatar_url ?? null);
          this.isLoading.set(false);
        },
        error: () => {
          this.hasLoadError.set(true);
          this.isLoading.set(false);
        },
      });

    this.twoFactorService
      .getStatus()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.twoFactorEnabled.set(response.data.enabled ?? false);
        },
      });
  }

  updateProfile(): void {
    if (this.profileForm.invalid || this.isUpdatingProfile()) {
      this.profileForm.markAllAsTouched();
      return;
    }

    const payload: UpdateProfilePayload = {
      name: this.profileForm.controls.name.value?.trim() ?? '',
    };

    this.isUpdatingProfile.set(true);
    this.profileErrorMessage.set('');

    this.profileService
      .updateProfile(payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.isUpdatingProfile.set(false);
          this.toast.success('Perfil atualizado com sucesso!');
          this.authStore.updateUser({ name: response.data.name });
        },
        error: (error) => {
          this.isUpdatingProfile.set(false);
          this.profileErrorMessage.set(
            error.error?.message || 'Não foi possível atualizar o perfil.',
          );
        },
      });
  }

  updatePassword(): void {
    if (this.passwordForm.invalid || this.isUpdatingPassword()) {
      this.passwordForm.markAllAsTouched();
      return;
    }

    const payload: UpdatePasswordPayload = {
      current_password: this.passwordForm.controls.current_password.value?.trim() ?? '',
      password: this.passwordForm.controls.password.value?.trim() ?? '',
      password_confirmation: this.passwordForm.controls.password_confirmation.value?.trim() ?? '',
    };

    this.isUpdatingPassword.set(true);
    this.passwordErrorMessage.set('');

    this.profileService
      .updatePassword(payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isUpdatingPassword.set(false);
          this.toast.success('Senha alterada com sucesso!');
          this.passwordForm.reset();
        },
        error: (error) => {
          this.isUpdatingPassword.set(false);
          this.passwordErrorMessage.set(
            error.error?.message || 'Não foi possível alterar a senha.',
          );
        },
      });
  }

  private uploadAvatarFile(file: File): void {
    const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    if (!validTypes.includes(file.type)) {
      this.avatarError.set('Formato inválido. Use JPEG, PNG, WebP ou GIF.');
      return;
    }
    const maxSize = 2 * 1024 * 1024; // 2 MB
    if (file.size > maxSize) {
      this.avatarError.set('Arquivo muito grande. Tamanho máximo é 2 MB.');
      return;
    }

    this.avatarError.set('');
    this.isUploadingAvatar.set(true);

    const formData = new FormData();
    formData.append('image', file);

    this.profileService
      .updateProfileImage(formData)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.isUploadingAvatar.set(false);
          this.avatarUrl.set(response.data.avatar_url ?? null);
          this.authStore.updateUser({ avatar_url: response.data.avatar_url });
          this.avatarFileControl.setValue(null, { emitEvent: false });
          this.toast.success('Foto de perfil atualizada com sucesso!');
        },
        error: (error) => {
          this.isUploadingAvatar.set(false);
          this.avatarError.set(error.error?.message || 'Não foi possível enviar a imagem.');
        },
      });
  }

  // ── Error helpers ─────────────────────────────────────────────────────────────

  profileError(field: string): string {
    const c = this.profileForm.get(field);
    if (!c || !c.touched || !c.errors) return '';
    if (c.errors['required']) return 'Campo obrigatório.';
    return '';
  }

  passwordError(field: string): string {
    const c = this.passwordForm.get(field);
    if (!c || !c.touched || !c.errors) return '';
    if (c.errors['required']) return 'Campo obrigatório.';
    if (c.errors['minlength']) return 'A senha deve ter no mínimo 8 caracteres.';
    return '';
  }

  passwordMismatch(): boolean {
    const ctrl = this.passwordForm.controls.password_confirmation;
    return !!ctrl.touched && !!ctrl.errors?.['passwordMismatch'];
  }

  // ── Helpers ──────────────────────────────────────────────────────────────────

  private getInitials(name: string): string {
    return getInitials(name, '??');
  }
}

/** Custom validator — checks password === password_confirmation */
function matchPasswords(group: AbstractControl): ValidationErrors | null {
  const password = group.get('password')?.value;
  const confirm = group.get('password_confirmation')?.value;
  const confirmCtrl = group.get('password_confirmation');

  if (password && confirm && password !== confirm) {
    confirmCtrl?.setErrors({ passwordMismatch: true });
    return { passwordMismatch: true };
  }

  if (confirmCtrl?.errors?.['passwordMismatch']) {
    const { passwordMismatch: _, ...rest } = confirmCtrl.errors;
    confirmCtrl.setErrors(Object.keys(rest).length > 0 ? rest : null);
  }

  return null;
}
