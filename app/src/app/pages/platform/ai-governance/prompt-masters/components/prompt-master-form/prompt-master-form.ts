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
import {
  AfAlertComponent,
  AfTextInputComponent,
  AfTextareaInputComponent,
} from '@shared/components';
import { type MasterPrompt } from '@ai/models/ai.model';
import { AiGovernanceService } from '@core/services/ai-governance.service';

/**
 * Modal form for creating/editing master prompts.
 */
@Component({
  selector: 'app-prompt-master-form',
  standalone: true,
  imports: [ReactiveFormsModule, AfTextInputComponent, AfTextareaInputComponent, AfAlertComponent],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './prompt-master-form.html',
})
export class PromptMasterFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly service = inject(AiGovernanceService);
  private readonly destroyRef = inject(DestroyRef);

  readonly master = input<MasterPrompt | null>(null);

  readonly saved = output<MasterPrompt>();
  readonly cancelled = output<void>();

  readonly isSaving = signal(false);
  readonly errorMessage = signal<string | null>(null);

  readonly isFormValid = computed(() => this.form.valid);

  readonly form = this.fb.nonNullable.group({
    name: ['', [Validators.required, Validators.maxLength(255)]],
    content: ['', [Validators.required, Validators.minLength(10)]],
  });

  constructor() {
    effect(() => {
      const current = this.master();
      this.form.reset({
        name: current?.name ?? '',
        content: current?.content ?? '',
      });
    });
  }

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.isSaving.set(true);
    this.errorMessage.set(null);

    const payload = {
      name: this.form.controls.name.value,
      content: this.form.controls.content.value,
    };

    const request$ = this.master()
      ? this.service.updateMaster(this.master()!.id, payload)
      : this.service.createMaster(payload);

    request$.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        this.isSaving.set(false);
        this.saved.emit(response);
      },
      error: () => {
        this.isSaving.set(false);
        this.errorMessage.set('Não foi possível salvar. Verifique os dados e tente novamente.');
      },
    });
  }

  cancel(): void {
    this.cancelled.emit();
  }

  hasValidData(): boolean {
    return this.isFormValid();
  }
}
