import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  effect,
  inject,
  signal,
} from '@angular/core';
import { FormBuilder, type FormControl, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfCheckboxInputComponent,
  AfLoadingButtonComponent,
  AfPageTitleComponent,
  AfRadioInputComponent,
  AfSelectInputComponent,
  AfSkeletonComponent,
  AfSwitchInputComponent,
} from '@shared/components';
import { NotificationPreferencesService } from '@core/services/notification-preferences.service';
import { PreferencesStore } from '@core/services/preferences.store';
import { ThemeService } from '@core/services/theme.service';
import { ToastService } from '@core/services/toast.service';
import {
  type UserPreferences,
  type AppearancePreferences,
  type BehaviorPreferences,
  type CrmDefaultsPreferences,
  type AccessibilityPreferences,
  type NotificationPreference,
  type NotificationPreferencesBulkPayload,
} from '@shared/models/preferences.model';

/**
 * User preferences page — appearance, behavior, CRM defaults, accessibility, and security.
 * Uses manual save strategy: changes mark the store as dirty; a single Save button persists all.
 */
@Component({
  selector: 'app-preferences',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfPageTitleComponent,
    AfSkeletonComponent,
    AfLoadingButtonComponent,
    AfSwitchInputComponent,
    AfCheckboxInputComponent,
    AfRadioInputComponent,
    AfSelectInputComponent,
    AfButtonComponent,
    AfAlertComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './preferences.html',
})
export class PreferencesComponent implements OnInit {
  readonly store: PreferencesStore = inject(PreferencesStore);
  private readonly fb: FormBuilder = inject(FormBuilder);
  private readonly toast: ToastService = inject(ToastService);
  private readonly themeService: ThemeService = inject(ThemeService);
  private readonly destroyRef: DestroyRef = inject(DestroyRef);
  private readonly notifService: NotificationPreferencesService = inject(NotificationPreferencesService);

  // Track whether we have populated the form once from loaded preferences
  private formSeeded = signal(false);

  /** Notification preferences loaded from the backend. */
  private readonly notifPrefs = signal<NotificationPreference[] | null>(null);

  // ── Form sections ────────────────────────────────────────────────────────────

  readonly appearanceForm = this.fb.group({
    theme: this.fb.control<'light' | 'dark' | 'system'>('system', { nonNullable: true }),
    density: this.fb.control<'compact' | 'normal' | 'expanded'>('normal', { nonNullable: true }),
    fontSize: this.fb.control<'small' | 'medium' | 'large'>('medium', { nonNullable: true }),
  });

  readonly behaviorForm = this.fb.group({
    sound: this.fb.control<boolean>(true, { nonNullable: true }),
    chatNotify: this.fb.control<boolean>(true, { nonNullable: true }),
    quickReply: this.fb.control<boolean>(false, { nonNullable: true }),
    confirmBulk: this.fb.control<boolean>(true, { nonNullable: true }),
    ticketOpenMode: this.fb.control<'modal' | 'page'>('modal', { nonNullable: true }),
  });

  readonly crmDefaultsForm = this.fb.group({
    negotiationType: this.fb.control<'basic' | 'advanced' | 'full'>('basic', { nonNullable: true }),
    taskStatus: this.fb.control<'pending' | 'in_progress' | 'done'>('pending', {
      nonNullable: true,
    }),
    pipelineView: this.fb.control<'kanban' | 'list'>('kanban', { nonNullable: true }),
    negotiationOrder: this.fb.control<'date' | 'value' | 'probability'>('date', {
      nonNullable: true,
    }),
  });

  readonly accessibilityForm = this.fb.group({
    highContrast: this.fb.control<boolean>(false, { nonNullable: true }),
    reducedMotion: this.fb.control<boolean>(false, { nonNullable: true }),
  });

  readonly securityForm = this.fb.group({
    sessionTimeout: this.fb.control<number | null>(60),
  });

  // ── Notification grid ───────────────────────────────────────────────────────

  readonly notificationChannels = [
    { value: 'ui', label: 'UI' },
    { value: 'email', label: 'E-mail' },
    { value: 'push', label: 'Push' },
    { value: 'whatsapp', label: 'WhatsApp' },
  ] as const;

  readonly notificationTypes = [
    { value: 'new_ticket', label: 'Novo ticket' },
    { value: 'ticket_assigned', label: 'Ticket atribuído' },
    { value: 'ticket_updated', label: 'Ticket atualizado' },
    { value: 'ticket_closed', label: 'Ticket encerrado' },
    { value: 'reminder', label: 'Lembrete' },
    { value: 'event', label: 'Evento' },
    { value: 'mention', label: 'Menção' },
    { value: 'system', label: 'Sistema' },
    { value: 'billing', label: 'Cobrança' },
  ] as const;

  notificationControls: Record<string, FormControl<boolean>> = {};
  readonly notificationsEnabled = this.fb.control<boolean>(true, { nonNullable: true });

  // ── Options ─────────────────────────────────────────────────────────────────

  readonly themeOptions = [
    { value: 'light', label: 'Claro' },
    { value: 'dark', label: 'Escuro' },
    { value: 'system', label: 'Sistema' },
  ];

  readonly densityOptions = [
    { value: 'compact', label: 'Compacta' },
    { value: 'normal', label: 'Normal' },
    { value: 'expanded', label: 'Expandida' },
  ];

  readonly fontSizeOptions = [
    { value: 'small', label: 'Pequena' },
    { value: 'medium', label: 'Normal' },
    { value: 'large', label: 'Grande' },
  ];

  readonly ticketOpenModeOptions = [
    { value: 'modal', label: 'Modal' },
    { value: 'page', label: 'Página inteira' },
  ];

  readonly negotiationTypeOptions = [
    { value: 'basic', label: 'Básico' },
    { value: 'advanced', label: 'Avançado' },
    { value: 'full', label: 'Completo' },
  ];

  readonly taskStatusOptions = [
    { value: 'pending', label: 'Pendente' },
    { value: 'in_progress', label: 'Em andamento' },
    { value: 'done', label: 'Concluído' },
  ];

  readonly pipelineViewOptions = [
    { value: 'kanban', label: 'Kanban' },
    { value: 'list', label: 'Lista' },
  ];

  readonly negotiationOrderOptions = [
    { value: 'date', label: 'Data (recente)' },
    { value: 'value', label: 'Valor' },
    { value: 'probability', label: 'Probabilidade' },
  ];

  readonly sessionTimeoutOptions = [
    { value: 30, label: '30 minutos' },
    { value: 60, label: '1 hora' },
    { value: 120, label: '2 horas' },
    { value: 0, label: 'Nunca' },
  ];

  // ── Lifecycle ───────────────────────────────────────────────────────────────

  constructor() {
    // When store finishes loading, seed the form once (avoiding infinite loop)
    effect(
      () => {
        const prefs = this.store.preferences();
        const isLoading = this.store.isLoading();
        const hasLoadedOnce = this.formSeeded();

        if (!isLoading && prefs && !hasLoadedOnce) {
          this.seedFormFromPreferences(prefs);
          this.formSeeded.set(true);
        }
      },
      { allowSignalWrites: true },
    );

    // On save success, show toast
    effect(
      () => {
        const isSaving = this.store.isSaving();
        const error = this.store.error();
        // When isSaving transitions from true to false, check result
        if (!isSaving && this.lastSavingState) {
          if (!error) {
            this.toast.success('Preferências salvas com sucesso!');
          } else if (error) {
            this.toast.error(error);
          }
          this.lastSavingState = false;
        }
        if (isSaving) {
          this.lastSavingState = true;
        }
      },
      { allowSignalWrites: true },
    );
  }

  private lastSavingState = false;

  ngOnInit(): void {
    this.buildNotificationControls();
    this.subscribeToChanges();
    this.store.load();
    this.loadNotificationPreferences();
  }

  // ── Data loading ────────────────────────────────────────────────────────────

  private seedFormFromPreferences(prefs: UserPreferences): void {
    this.appearanceForm.patchValue(prefs.appearance, { emitEvent: false });
    this.behaviorForm.patchValue(prefs.behavior, { emitEvent: false });
    this.crmDefaultsForm.patchValue(prefs.crmDefaults, { emitEvent: false });
    this.accessibilityForm.patchValue(prefs.accessibility, { emitEvent: false });
    // Fix: backend stores null for "Nunca"; the select uses 0 as sentinel for null
    this.securityForm.patchValue(
      { sessionTimeout: prefs.security.sessionTimeout === null ? 0 : prefs.security.sessionTimeout },
      { emitEvent: false },
    );
    // Apply appearance side-effects immediately from saved preferences
    this.themeService.applyTheme(prefs.appearance.theme);
    this.themeService.applyDensity(prefs.appearance.density);
    this.themeService.applyFontSize(prefs.appearance.fontSize);
  }

  // ── Actions ─────────────────────────────────────────────────────────────────

  save(): void {
    if (this.store.isSaving()) {
      return;
    }

    const appearance = this.appearanceForm.getRawValue();
    const behavior = this.behaviorForm.getRawValue();
    const crmDefaults = this.crmDefaultsForm.getRawValue();
    const accessibility = this.accessibilityForm.getRawValue();
    const rawSecurity = this.securityForm.getRawValue();
    // Convert 0 (sentinel) back to null for "Nunca" selection
    const security = {
      sessionTimeout: rawSecurity.sessionTimeout === 0 ? null : rawSecurity.sessionTimeout,
    };

    const prefs: UserPreferences = {
      appearance,
      behavior,
      crmDefaults,
      security,
      accessibility,
    };

    this.store.save(prefs);

    // Save notification preferences in parallel (independent operation)
    if (this.notifPrefs() !== null) {
      this.saveNotificationPreferences();
    }
  }

  reset(): void {
    this.appearanceForm.reset();
    this.behaviorForm.reset();
    this.crmDefaultsForm.reset();
    this.accessibilityForm.reset();
    this.securityForm.reset();
    this.formSeeded.set(false);
    this.store.reset();
  }

  // ── Notification preferences ────────────────────────────────────────────────

  private loadNotificationPreferences(): void {
    this.notifService
      .getPreferences()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.notifPrefs.set(response.data);
          this.seedNotificationControls(response.data);
        },
        error: () => {
          // Non-blocking: notification load failure does not prevent using the page
        },
      });
  }

  private seedNotificationControls(prefs: NotificationPreference[]): void {
    const prefsByType = new Map(prefs.map((p) => [p.notification_type, p]));
    const anyEnabled = prefs.some((p) => p.enabled);
    this.notificationsEnabled.setValue(anyEnabled, { emitEvent: false });

    for (const type of this.notificationTypes) {
      const pref = prefsByType.get(type.value);
      for (const channel of this.notificationChannels) {
        const key = `${type.value}_${channel.value}`;
        const isChecked = pref ? (pref.channels ?? []).includes(channel.value) : false;
        this.notificationControls[key]?.setValue(isChecked, { emitEvent: false });
      }
    }
  }

  private buildNotificationPayload(): NotificationPreferencesBulkPayload {
    const masterEnabled = this.notificationsEnabled.value;
    const preferences = this.notificationTypes.map((type) => {
      const channels = this.notificationChannels
        .filter((ch) => this.notificationControls[`${type.value}_${ch.value}`]?.value)
        .map((ch) => ch.value);
      return {
        type: type.value,
        channels,
        enabled: masterEnabled && channels.length > 0,
      };
    });
    return { preferences };
  }

  private saveNotificationPreferences(): void {
    const payload = this.buildNotificationPayload();
    this.notifService
      .updateAllPreferences(payload)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.notifPrefs.set(response.data);
        },
        error: () => {
          this.toast.error('Erro ao salvar preferências de notificação');
        },
      });
  }

  // ── Appearance side-effects ─────────────────────────────────────────────────

  applyTheme(theme: 'light' | 'dark' | 'system'): void {
    this.themeService.applyTheme(theme);
  }

  applyDensity(density: 'compact' | 'normal' | 'expanded'): void {
    this.themeService.applyDensity(density);
  }

  applyFontSize(size: 'small' | 'medium' | 'large'): void {
    this.themeService.applyFontSize(size);
  }

  // ── Helpers ─────────────────────────────────────────────────────────────────

  private buildNotificationControls(): void {
    for (const type of this.notificationTypes) {
      for (const channel of this.notificationChannels) {
        const key = `${type.value}_${channel.value}`;
        this.notificationControls[key] = this.fb.control<boolean>(true, { nonNullable: true });
      }
    }
  }

  private subscribeToChanges(): void {
    // Appearance: mark dirty + apply changes immediately so user sees preview
    this.appearanceForm.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe(() => {
      this.store.markDirty();
      const { theme, density, fontSize } = this.appearanceForm.getRawValue();
      if (theme) this.applyTheme(theme);
      if (density) this.applyDensity(density);
      if (fontSize) this.applyFontSize(fontSize);
    });

    // Other forms: mark dirty only
    this.behaviorForm.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.store.markDirty());

    this.crmDefaultsForm.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.store.markDirty());

    this.accessibilityForm.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.store.markDirty());

    this.securityForm.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.store.markDirty());

    for (const key of Object.keys(this.notificationControls)) {
      this.notificationControls[key].valueChanges
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe(() => this.store.markDirty());
    }

    this.notificationsEnabled.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => this.store.markDirty());
  }
}
