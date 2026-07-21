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
  AfColorInputComponent,
  AfSwitchInputComponent,
  AfTextInputComponent,
} from '@shared/components';
import { TagService } from '@core/services/tag.service';
import type { Tag } from '@core/models/tag.model';

  /**
   * Evento emitido quando o formulário é cancelado.
   */
@Component({
  selector: 'app-tag-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfTextInputComponent,
    AfColorInputComponent,
    AfSwitchInputComponent,
    AfAlertComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './tag-form.html',
})
export class TagFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly tagService = inject(TagService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly lastLoadedId = signal<string | null>(null);

  /**
   * Tag para edição — null para modo de criação.
   */
  readonly tag = input<Tag | null>(null);

  /**
   * Evento emitido após salvar com sucesso.
   */
  readonly saved = output<Tag>();

  /**
   * Evento emitido quando o usuário cancela.
   */
  readonly cancelled = output<void>();

  /**
   * Estado de carregamento do salvamento — acessado pelo pai via viewChild.
   */
  readonly isSaving = signal(false);

  /**
   * Mensagem de erro inline.
   */
  readonly errorMessage = signal<string | null>(null);

  readonly form = this.fb.group({
    name: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    color: this.fb.control('#2fc85a', { nonNullable: true, validators: [Validators.required] }),
    category: this.fb.control('', { nonNullable: true }),
    is_active: this.fb.control(true, { nonNullable: true }),
  });

  constructor() {
    effect(() => {
      const item = this.tag();
      if (item) {
        if (this.lastLoadedId() === item.id) return;
        this.lastLoadedId.set(item.id);
        this.form.reset({
          name: item.name,
          color: item.color,
          category: item.category ?? '',
          is_active: item.is_active,
        });
      } else {
        this.lastLoadedId.set(null);
        this.resetForm();
      }
    });
  }

  /**
   * Submete o formulário — valida, constrói payload e chama a API.
   */
  submit(): void {
    if (this.form.invalid || this.isSaving()) {
      this.form.markAllAsTouched();
      return;
    }

    const formValue = this.form.getRawValue();
    const payload = {
      name: formValue.name,
      color: formValue.color,
      category: formValue.category || undefined,
      is_active: formValue.is_active,
    };

    const editing = this.tag();
    const request = editing
      ? this.tagService.update(editing.id, payload)
      : this.tagService.create(payload);

    this.isSaving.set(true);
    this.errorMessage.set(null);

    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        this.isSaving.set(false);
        this.resetForm();
        this.saved.emit(response.data);
      },
      error: () => {
        this.isSaving.set(false);
        this.errorMessage.set('Não foi possível salvar a etiqueta. Tente novamente.');
      },
    });
  }

  /**
   * Cancela — emite evento de cancelamento.
   */
  cancel(): void {
    this.cancelled.emit();
  }

  private resetForm(): void {
    this.form.reset({
      name: '',
      color: '#2fc85a',
      category: '',
      is_active: true,
    });
    this.errorMessage.set(null);
  }
}
