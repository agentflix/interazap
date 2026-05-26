import {
  type OnInit,
  ChangeDetectionStrategy,
  ChangeDetectorRef,
  Component,
  DestroyRef,
  inject,
  signal,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed, toSignal } from '@angular/core/rxjs-interop';
import { map, startWith } from 'rxjs';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfLoadingButtonComponent,
  AfPageTitleComponent,
  AfSelectInputComponent,
  AfSkeletonComponent,
  AfSwitchInputComponent,
} from '@shared/components';
import { SchedulingSettingsService } from '@core/services/configuration/scheduling-setting.service';
import { ToastService } from '@core/services/toast.service';
import type { SchedulingSettings } from '@core/models/configuration/scheduling-setting.model';

/**
 * Página de configurações de agendamento — controla confirmação de eventos
 * e notificações de lembrete com antecedência configurável.
 */
@Component({
  selector: 'app-scheduling-settings',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfPageTitleComponent,
    AfSkeletonComponent,
    AfLoadingButtonComponent,
    AfAlertComponent,
    AfButtonComponent,
    AfSelectInputComponent,
    AfSwitchInputComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './scheduling-settings.component.html',
})
export class SchedulingSettingsComponent implements OnInit {
  private readonly settingsService = inject(SchedulingSettingsService);
  private readonly toast = inject(ToastService);
  private readonly fb = inject(FormBuilder);
  private readonly destroyRef = inject(DestroyRef);
  private readonly cdr = inject(ChangeDetectorRef);

  // ── State ────────────────────────────────────────────────────────────────

  readonly isLoading = signal(false);
  readonly isSaving = signal(false);
  readonly error = signal<string | null>(null);

  // ── Form ─────────────────────────────────────────────────────────────────

  readonly form = this.fb.group({
    event_confirmation_advance_minutes: this.fb.control<number>(1440, { nonNullable: true }),
    event_confirmation_enabled: this.fb.control<boolean>(true, { nonNullable: true }),
    event_confirmation_notify_ui: this.fb.control<boolean>(true, { nonNullable: true }),
    event_confirmation_notify_push: this.fb.control<boolean>(true, { nonNullable: true }),
  });

  // ── Derived signals ──────────────────────────────────────────────────────

  readonly isEnabled = toSignal(
    this.form.controls.event_confirmation_enabled.valueChanges.pipe(
      startWith(this.form.controls.event_confirmation_enabled.value),
      map((enabled) => enabled === true),
    ),
    { initialValue: false },
  );

  // ── Options ──────────────────────────────────────────────────────────────

  readonly advanceMinutesOptions = [
    { value: 15, label: '15 minutos' },
    { value: 30, label: '30 minutos' },
    { value: 60, label: '1 hora' },
    { value: 120, label: '2 horas' },
    { value: 360, label: '6 horas' },
    { value: 720, label: '12 horas' },
    { value: 1440, label: '24 horas' },
    { value: 2880, label: '48 horas' },
    { value: 4320, label: '72 horas' },
  ];

  // ── Lifecycle ────────────────────────────────────────────────────────────

  ngOnInit(): void {
    this.loadSettings();
  }

  // ── Data loading ─────────────────────────────────────────────────────────

  /** Carrega as configurações de agendamento da API e preenche o formulário. */
  loadSettings(): void {
    this.isLoading.set(true);
    this.error.set(null);

    this.settingsService.getSettings().pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        const settings = response.data;
        this.form.patchValue(
          {
            event_confirmation_advance_minutes: settings.event_confirmation_advance_minutes,
            event_confirmation_enabled: settings.event_confirmation_enabled,
            event_confirmation_notify_ui: settings.event_confirmation_notify_ui,
            event_confirmation_notify_push: settings.event_confirmation_notify_push,
          },
          { emitEvent: false },
        );
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

  // ── Actions ──────────────────────────────────────────────────────────────

  /** Salva as configurações de agendamento via API após validar o formulário. */
  save(): void {
    if (this.isSaving()) {
      return;
    }

    this.isSaving.set(true);
    this.error.set(null);

    const payload: Partial<SchedulingSettings> = this.form.getRawValue();

    this.settingsService.updateSettings(payload).pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => {
        this.isSaving.set(false);
        this.toast.success('Configurações de agendamento salvas com sucesso!');
        this.cdr.markForCheck();
      },
      error: (err) => {
        this.error.set(err.error?.message ?? 'Não foi possível salvar as configurações.');
        this.isSaving.set(false);
        this.cdr.markForCheck();
      },
    });
  }

  /** Descarta alterações não salvas e recarrega as configurações da API. */
  reset(): void {
    this.form.reset();
    this.loadSettings();
  }
}
