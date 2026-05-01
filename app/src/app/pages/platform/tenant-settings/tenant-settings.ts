import {
  type OnInit,
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  DestroyRef,
  inject,
  signal,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { map, startWith } from 'rxjs';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfLoadingButtonComponent,
  AfPageTitleComponent,
  AfRadioInputComponent,
  AfSelectInputComponent,
  AfSkeletonComponent,
  AfSwitchInputComponent,
  AfTextareaInputComponent,
} from '@shared/components';
import { TenantSettingsService } from '@core/services/tenant-settings.service';
import { ToastService } from '@core/services/toast.service';
import { AuthStoreService } from '@core/services/auth-store.service';
import type {
  TenantChatAutoCloseSettings,
  TenantSettings,
} from '@shared/models/tenant-settings.model';

/**
 * Tenant settings page — localization, privacy, and auto-close configuration.
 * Accessible only by admins/managers with `platform.tenants.manage` permission.
 */
@Component({
  selector: 'app-tenant-settings',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfPageTitleComponent,
    AfSkeletonComponent,
    AfLoadingButtonComponent,
    AfAlertComponent,
    AfButtonComponent,
    AfRadioInputComponent,
    AfSelectInputComponent,
    AfSwitchInputComponent,
    AfTextareaInputComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './tenant-settings.html',
})
export class TenantSettingsComponent implements OnInit {
  private readonly settingsService = inject(TenantSettingsService);
  private readonly toast = inject(ToastService);
  private readonly authStore = inject(AuthStoreService);
  private readonly fb = inject(FormBuilder);
  private readonly destroyRef = inject(DestroyRef);
  private readonly cdr = inject(ChangeDetectorRef);

  // ── State ────────────────────────────────────────────────────────────────

  readonly isLoading = signal(false);
  readonly isSaving = signal(false);
  readonly error = signal<string | null>(null);

  // ── Forms ────────────────────────────────────────────────────────────────

  readonly localizationForm = this.fb.group({
    timezone: this.fb.control<string>('America/Sao_Paulo', { nonNullable: true }),
    dateFormat: this.fb.control<'DD/MM/YYYY' | 'MM/DD/YYYY' | 'YYYY-MM-DD'>('DD/MM/YYYY', {
      nonNullable: true,
    }),
    timeFormat: this.fb.control<'12h' | '24h'>('24h', { nonNullable: true }),
    currencyFormat: this.fb.control<'BRL' | 'USD' | 'EUR'>('BRL', { nonNullable: true }),
  });

  readonly privacyForm = this.fb.group({
    presence: this.fb.control<'all' | 'team' | 'hidden'>('all', { nonNullable: true }),
    readReceipt: this.fb.control<boolean>(true, { nonNullable: true }),
    notificationPreview: this.fb.control<boolean>(true, { nonNullable: true }),
  });

  readonly chatForm = this.fb.group({
    auto_close_inactivity_enabled: this.fb.control(false, { nonNullable: true }),
    auto_close_inactivity_minutes: this.fb.control(30, {
      nonNullable: true,
      validators: [Validators.required],
    }),
    auto_close_inactivity_target: this.fb.control<'both' | 'client' | 'agent'>('both', {
      nonNullable: true,
      validators: [Validators.required],
    }),
    auto_close_inactivity_message: this.fb.control(
      'Este atendimento foi encerrado automaticamente por inatividade.',
      { nonNullable: true, validators: [Validators.maxLength(2000)] },
    ),
  });

  // ── Derived signals ──────────────────────────────────────────────────────

  readonly isAutoCloseEnabled = toSignal(
    this.chatForm.controls.auto_close_inactivity_enabled.valueChanges.pipe(
      startWith(this.chatForm.controls.auto_close_inactivity_enabled.value),
      map((enabled) => enabled === true),
    ),
    { initialValue: false },
  );

  // ── Options ─────────────────────────────────────────────────────────────

  readonly timezoneOptions = [
    { value: 'America/Sao_Paulo', label: 'America/Sao_Paulo (GMT-3)' },
    { value: 'America/New_York', label: 'America/New_York (GMT-5)' },
    { value: 'America/Los_Angeles', label: 'America/Los_Angeles (GMT-8)' },
    { value: 'Europe/London', label: 'Europe/London (GMT+0)' },
    { value: 'Europe/Paris', label: 'Europe/Paris (GMT+1)' },
    { value: 'UTC', label: 'UTC (GMT+0)' },
    { value: 'Asia/Tokyo', label: 'Asia/Tokyo (GMT+9)' },
  ];

  readonly dateFormatOptions = [
    { value: 'DD/MM/YYYY', label: 'DD/MM/YYYY' },
    { value: 'MM/DD/YYYY', label: 'MM/DD/YYYY' },
    { value: 'YYYY-MM-DD', label: 'YYYY-MM-DD' },
  ];

  readonly timeFormatOptions = [
    { value: '12h', label: '12h' },
    { value: '24h', label: '24h' },
  ];

  readonly currencyFormatOptions = [
    { value: 'BRL', label: 'R$ 1.234,56' },
    { value: 'USD', label: '$ 1,234.56' },
    { value: 'EUR', label: '€ 1.234,56' },
  ];

  readonly presenceOptions = [
    { value: 'all', label: 'Visível para todos' },
    { value: 'team', label: 'Somente equipe' },
    { value: 'hidden', label: 'Oculto' },
  ];

  readonly inactivityMinutesOptions = [
    { value: 5, label: 'Após 5 minutos' },
    { value: 10, label: 'Após 10 minutos' },
    { value: 15, label: 'Após 15 minutos' },
    { value: 30, label: 'Após 30 minutos' },
    { value: 45, label: 'Após 45 minutos' },
    { value: 60, label: 'Após 1 hora' },
    { value: 120, label: 'Após 2 horas' },
  ];

  readonly inactivityTargetOptions = [
    { value: 'both', label: 'Ambos (atendente e cliente)' },
    { value: 'client', label: 'Apenas cliente' },
    { value: 'agent', label: 'Apenas atendente' },
  ];

  // ── Lifecycle ───────────────────────────────────────────────────────────

  ngOnInit(): void {
    this.loadSettings();
  }

  // ── Data loading ────────────────────────────────────────────────────────

  loadSettings(): void {
    const tenantId = this.authStore.user()?.tenant_id;
    if (!tenantId) {
      this.error.set('Não foi possível identificar o inquilino.');
      return;
    }

    this.isLoading.set(true);
    this.error.set(null);

    this.settingsService
      .getSettings(String(tenantId))
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const settings = response.data;
          this.localizationForm.patchValue(settings.settings_localization, { emitEvent: false });
          this.privacyForm.patchValue(settings.settings_privacy, { emitEvent: false });
          if (settings.settings_chat) {
            this.chatForm.patchValue(settings.settings_chat);
          }
          this.isLoading.set(false);
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.error.set(err.error?.message ?? 'Não foi possível carregar as configurações.');
          this.isLoading.set(false);
          this.cdr.markForCheck();
        },
      });
  }

  // ── Actions ─────────────────────────────────────────────────────────────

  save(): void {
    if (this.isSaving()) {
      return;
    }

    const tenantId = this.authStore.user()?.tenant_id;
    if (!tenantId) {
      this.toast.error('Não foi possível identificar o inquilino.');
      return;
    }

    this.isSaving.set(true);
    this.error.set(null);

    if (this.isAutoCloseEnabled()) {
      this.chatForm.markAllAsTouched();
      if (this.chatForm.invalid) {
        this.isSaving.set(false);
        return;
      }
    }

    const settings: Partial<TenantSettings> = {
      settings_localization: this.localizationForm.getRawValue(),
      settings_privacy: this.privacyForm.getRawValue(),
      settings_chat: this.chatForm.getRawValue() as TenantChatAutoCloseSettings,
    };

    this.settingsService
      .updateSettings(String(tenantId), settings)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isSaving.set(false);
          this.toast.success('Configurações salvas com sucesso!');
          this.cdr.markForCheck();
        },
        error: (err) => {
          this.error.set(err.error?.message ?? 'Não foi possível salvar as configurações.');
          this.isSaving.set(false);
          this.cdr.markForCheck();
        },
      });
  }

  reset(): void {
    this.localizationForm.reset();
    this.privacyForm.reset();
    this.chatForm.reset();
    this.loadSettings();
  }
}
