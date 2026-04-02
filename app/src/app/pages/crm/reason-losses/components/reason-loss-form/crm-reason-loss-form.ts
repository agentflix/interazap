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
  AfCheckboxInputComponent,
  AfSwitchInputComponent,
  AfTextInputComponent,
  AfTextareaInputComponent,
} from '@shared/components';
import { type ReasonLoss, ReasonLossService } from '@core/services/crm-reason-loss.service';

/**
 * Reason loss form component — create/edit loss reasons.
 * Business logic preserved verbatim from source.
 */
@Component({
  selector: 'app-reason-loss-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfAlertComponent,
    AfTextInputComponent,
    AfTextareaInputComponent,
    AfSwitchInputComponent,
    AfCheckboxInputComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './crm-reason-loss-form.html',
})
export class ReasonLossFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly reasonLossService = inject(ReasonLossService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly lastLoadedId = signal<string | null>(null);

  readonly reasonLoss = input<ReasonLoss | null>(null);
  readonly saved = output<ReasonLoss>();
  readonly cancelled = output<void>();

  readonly isSaving = signal(false);
  readonly errorMessage = signal<string | null>(null);

  readonly form = this.fb.group({
    name: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    description: this.fb.control('', { nonNullable: true }),
    requires_comment: this.fb.control(false, {
      nonNullable: true,
      validators: [Validators.required],
    }),
    is_active: this.fb.control(true, { nonNullable: true }),
  });

  constructor() {
    effect(() => {
      const item = this.reasonLoss();
      if (item) {
        if (this.lastLoadedId() === item.id) return;
        this.lastLoadedId.set(item.id);
        this.form.reset({
          name: item.name,
          description: item.description ?? '',
          requires_comment: item.requires_comment,
          is_active: item.is_active,
        });
      } else {
        this.lastLoadedId.set(null);
        this.resetForm();
      }
    });
  }

  submit(): void {
    if (this.form.invalid || this.isSaving()) {
      this.form.markAllAsTouched();
      return;
    }

    const fv = this.form.getRawValue();
    const payload = {
      name: fv.name,
      description: fv.description || undefined,
      requires_comment: fv.requires_comment,
      is_active: fv.is_active,
    };

    const editing = this.reasonLoss();
    const request$ = editing
      ? this.reasonLossService.update(editing.id, payload)
      : this.reasonLossService.create(payload);

    this.isSaving.set(true);
    this.errorMessage.set(null);

    request$.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        this.isSaving.set(false);
        this.saved.emit(response.data);
      },
      error: () => {
        this.isSaving.set(false);
        this.errorMessage.set('Não foi possível salvar o motivo de perda. Tente novamente.');
      },
    });
  }

  cancel(): void {
    this.cancelled.emit();
  }

  private resetForm(): void {
    this.form.reset({
      name: '',
      description: '',
      requires_comment: false,
      is_active: true,
    });
    this.errorMessage.set(null);
  }
}
