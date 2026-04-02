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
import { NonNullableFormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { toast } from 'ngx-sonner';
import { type QuickAnswer, QuickAnswerService } from '@core/services/quick-answer.service';
import {
  AfTextInputComponent,
  AfTextareaInputComponent,
  AfSwitchInputComponent,
} from '@shared/components';

/**
 * Formulario para criacao/edicao de respostas rapidas.
 *
 * @remarks
 * Logica TS preservada verbatim do legado; apenas camada visual migrada para UI Kit.
 * Suporta campos de nome, conteudo, atalho opcional e status ativo.
 *
 * @example
 * ```html
 * <app-quick-answer-form
 *   [quickAnswer]="quickAnswer"
 *   (saved)="onSaved($event)"
 *   (cancelled)="onCancelled()" />
 * ```
 */
@Component({
  selector: 'app-quick-answer-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfTextInputComponent,
    AfTextareaInputComponent,
    AfSwitchInputComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './quick-answer-form.html',
})
export class QuickAnswerFormComponent {
  private readonly fb = inject(NonNullableFormBuilder);
  private readonly quickAnswerService = inject(QuickAnswerService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly lastLoadedId = signal<string | null>(null);

  readonly quickAnswer = input<QuickAnswer | null>(null);
  readonly saved = output<QuickAnswer>();
  readonly cancelled = output<void>();

  readonly isSaving = signal(false);

  readonly form = this.fb.group({
    name: this.fb.control('', Validators.required),
    content: this.fb.control('', Validators.required),
    shortcut: this.fb.control(''),
    is_active: this.fb.control(true),
  });

  constructor() {
    effect(() => {
      const item = this.quickAnswer();
      if (item) {
        if (this.lastLoadedId() === String(item.id)) {
          return;
        }
        this.lastLoadedId.set(String(item.id));
        this.form.reset({
          name: item.name,
          content: item.content,
          shortcut: item.shortcut || '',
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

    const payload = this.form.getRawValue();
    const editing = this.quickAnswer();
    this.isSaving.set(true);

    const request = editing
      ? this.quickAnswerService.update(editing.id, payload)
      : this.quickAnswerService.create(payload);

    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        this.isSaving.set(false);
        this.saved.emit(response);
      },
      error: (error) => {
        this.isSaving.set(false);
        toast.error(error.error?.message || 'Não foi possível salvar a resposta.');
      },
    });
  }

  cancel(): void {
    this.cancelled.emit();
  }

  private resetForm(): void {
    this.form.reset({
      name: '',
      content: '',
      shortcut: '',
      is_active: true,
    });
  }
}
