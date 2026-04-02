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
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import {
  AfAlertComponent,
  AfSelectInputComponent,
  AfSwitchInputComponent,
  AfTextInputComponent,
  AfTextareaInputComponent,
  type AfSelectOption,
} from '@shared/components';
import { type SegmentPrompt } from '@ai/models/ai.model';
import { AiGovernanceService } from '@core/services/ai-governance.service';

/**
 * Modal form for creating/editing segment prompts.
 */
@Component({
  selector: 'app-prompt-segment-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfTextInputComponent,
    AfTextareaInputComponent,
    AfSelectInputComponent,
    AfSwitchInputComponent,
    AfAlertComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './prompt-segment-form.html',
})
export class PromptSegmentFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly service = inject(AiGovernanceService);
  private readonly destroyRef = inject(DestroyRef);

  readonly segment = input<SegmentPrompt | null>(null);
  readonly masterOptions = input<AfSelectOption[]>([]);

  readonly saved = output<SegmentPrompt>();
  readonly cancelled = output<void>();

  readonly isSaving = signal(false);
  readonly errorMessage = signal<string | null>(null);

  readonly form = this.fb.nonNullable.group({
    code: ['', [Validators.required, Validators.maxLength(100)]],
    name: ['', [Validators.required, Validators.maxLength(255)]],
    description: [''],
    content: ['', [Validators.required, Validators.minLength(10)]],
    master_id: ['', [Validators.required]],
    is_active: [true],
  });

  constructor() {
    effect(() => {
      const current = this.segment();
      this.form.reset({
        code: current?.code ?? '',
        name: current?.name ?? '',
        description: current?.description ?? '',
        content: current?.content ?? '',
        master_id: current?.master_id ?? '',
        is_active: current?.is_active ?? true,
      });

      if (current?.code === 'GENERAL') {
        this.form.controls.code.disable({ emitEvent: false });
        this.form.controls.is_active.disable({ emitEvent: false });
      } else {
        this.form.controls.code.enable({ emitEvent: false });
        this.form.controls.is_active.enable({ emitEvent: false });
      }
    });
  }

  submit(): void {
    if (this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.isSaving.set(true);
    this.errorMessage.set(null);

    const raw = this.form.getRawValue();
    const payload = {
      code: raw.code,
      name: raw.name,
      description: raw.description || undefined,
      content: raw.content,
      master_id: raw.master_id,
      is_active: raw.is_active,
    };

    const request$ = this.segment()
      ? this.service.updateSegment(this.segment()!.id, payload)
      : this.service.createSegment(payload);

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
    return this.form.valid;
  }
}
