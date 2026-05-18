import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  effect,
  inject,
  input,
  output,
  signal,
} from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import {
  AfAlertComponent,
  AfSelectInputComponent,
  AfSwitchInputComponent,
  AfTextInputComponent,
  type AfSelectOption,
} from '@shared/components';
import { OpeningHourService } from '@core/services/opening-hour.service';
import type { OpeningHour } from '@core/models/opening-hour.model';

/**
 * Opening-hours form for creating and editing a single day schedule.
 * Business logic preserved verbatim from source.
 * day_of_week is stored as string in the form and converted to number on submit.
 */
@Component({
  selector: 'app-opening-hours-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfSelectInputComponent,
    AfTextInputComponent,
    AfSwitchInputComponent,
    AfAlertComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './opening-hours-form.html',
})
export class OpeningHoursFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly openingHourService = inject(OpeningHourService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly lastLoadedId = signal<string | null>(null);

  /** Opening hour to edit — null for create mode */
  readonly openingHour = input<OpeningHour | null>(null);

  /** Emitted after a successful save */
  readonly saved = output<OpeningHour>();

  /** Emitted when user cancels */
  readonly cancelled = output<void>();

  /** Save loading state — accessed by parent via viewChild */
  readonly isSaving = signal(false);

  /** Inline error message */
  readonly errorMessage = signal<string | null>(null);

  /**
   * Day-of-week options — values are strings to match AfSelectOption contract.
   * Converted to number on submit (original API expects 0–6).
   */
  readonly dayOfWeekOptions: AfSelectOption[] = [
    { label: 'Domingo', value: '0' },
    { label: 'Segunda-feira', value: '1' },
    { label: 'Terça-feira', value: '2' },
    { label: 'Quarta-feira', value: '3' },
    { label: 'Quinta-feira', value: '4' },
    { label: 'Sexta-feira', value: '5' },
    { label: 'Sábado', value: '6' },
  ];

  readonly form = this.fb.group({
    /** String representation of day_of_week (0–6) for AfSelectInput compatibility */
    day_of_week: this.fb.control('1', { nonNullable: true, validators: [Validators.required] }),
    open_time: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    close_time: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    is_active: this.fb.control(true, { nonNullable: true }),
  });

  constructor() {
    effect(() => {
      const item = this.openingHour();
      if (item) {
        if (this.lastLoadedId() === item.id) return;
        this.lastLoadedId.set(item.id);
        this.form.reset({
          day_of_week: String(item.day_of_week),
          open_time: item.open_time.substring(0, 5),
          close_time: item.close_time.substring(0, 5),
          is_active: item.is_active,
        });
      } else {
        this.lastLoadedId.set(null);
        this.resetForm();
      }
    });
  }

  /** Submit the form — validates, builds payload (converts day_of_week to number), calls API */
  submit(): void {
    if (this.form.invalid || this.isSaving()) {
      this.form.markAllAsTouched();
      return;
    }

    const formValue = this.form.getRawValue();

    // Validate time ordering (original business rule)
    if (
      formValue.open_time &&
      formValue.close_time &&
      formValue.close_time <= formValue.open_time
    ) {
      this.errorMessage.set('O horário de fechamento deve ser após o horário de abertura.');
      return;
    }

    const payload: Partial<OpeningHour> = {
      day_of_week: Number(formValue.day_of_week),
      open_time: formValue.open_time,
      close_time: formValue.close_time,
      is_active: formValue.is_active,
    };

    const editing = this.openingHour();
    const request = editing
      ? this.openingHourService.update(editing.id, payload)
      : this.openingHourService.create(payload);

    this.isSaving.set(true);
    this.errorMessage.set(null);

    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        this.isSaving.set(false);
        this.resetForm();
        this.saved.emit(response.data);
      },
      error: (error) => {
        this.isSaving.set(false);
        this.errorMessage.set(this.getErrorMessage(error));
      },
    });
  }

  /** Cancel — emit cancelled event */
  cancel(): void {
    this.cancelled.emit();
  }

  private resetForm(): void {
    this.form.reset({
      day_of_week: '1',
      open_time: '09:00',
      close_time: '18:00',
      is_active: true,
    });
    this.errorMessage.set(null);
  }

  /** Extract human-readable error message from API response (original logic preserved) */
  private getErrorMessage(error: unknown): string {
    const fallback = 'Não foi possível salvar o horário.';
    if (!error || typeof error !== 'object') return fallback;

    const apiError = error as { error?: { message?: string; errors?: Record<string, string[]> } };
    const apiMessage = apiError.error?.message;
    if (typeof apiMessage === 'string') {
      const normalized = apiMessage.toLowerCase();
      if (normalized.includes('day of week') && normalized.includes('between 0 and 6')) {
        return 'O dia da semana deve estar entre 0 e 6.';
      }
      return apiMessage;
    }

    const fieldErrors = apiError.error?.errors;
    const dayError = fieldErrors?.['day_of_week']?.[0];
    if (typeof dayError === 'string') {
      const normalized = dayError.toLowerCase();
      if (normalized.includes('day of week') && normalized.includes('between 0 and 6')) {
        return 'O dia da semana deve estar entre 0 e 6.';
      }
    }

    return fallback;
  }
}
