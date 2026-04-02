import {
  type OnInit,
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  DestroyRef,
  inject,
  signal,
} from '@angular/core';
import { FormBuilder, FormControl, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
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
} from '@shared/components';
import { TenantSettingsService } from '@core/services/tenant-settings.service';
import { ToastService } from '@core/services/toast.service';
import { AuthStoreService } from '@core/services/auth-store.service';
import type { TenantSettings } from '@shared/models/tenant-settings.model';

/**
 * Tenant settings page — localization and privacy configuration.
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

  // ── Form ────────────────────────────────────────────────────────────────

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

  // ── Lifecycle ───────────────────────────────────────────────────────────

  ngOnInit(): void {
    this.subscribeToChanges();
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

    const settings: Partial<TenantSettings> = {
      settings_localization: this.localizationForm.getRawValue(),
      settings_privacy: this.privacyForm.getRawValue(),
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
    this.loadSettings();
  }

  // ── Helpers ─────────────────────────────────────────────────────────────

  private subscribeToChanges(): void {
    // Mark dirty: for simplicity, we track dirty state at the form level
    // by checking pristine status on submit
  }
}
