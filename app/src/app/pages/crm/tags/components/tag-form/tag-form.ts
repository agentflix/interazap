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
import { type Tag, TagService } from '@core/services/tag.service';

/**
 * Tag form component for creating and editing CRM tags.
 * Business logic preserved verbatim from source. Visual layer migrated to UI Kit.
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

  /** Tag to edit — null for create mode */
  readonly tag = input<Tag | null>(null);

  /** Emitted after a successful save */
  readonly saved = output<Tag>();

  /** Emitted when user cancels */
  readonly cancelled = output<void>();

  /** Save loading state — accessed by parent via viewChild */
  readonly isSaving = signal(false);

  /** Inline error message */
  readonly errorMessage = signal<string | null>(null);

  readonly form = this.fb.group({
    name: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    color: this.fb.control('#6366f1', { nonNullable: true, validators: [Validators.required] }),
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

  /** Submit the form — validates, builds payload, calls API */
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
        this.saved.emit(response.data);
      },
      error: () => {
        this.isSaving.set(false);
        this.errorMessage.set('Não foi possível salvar a etiqueta. Tente novamente.');
      },
    });
  }

  /** Cancel — emit cancelled event */
  cancel(): void {
    this.cancelled.emit();
  }

  private resetForm(): void {
    this.form.reset({
      name: '',
      color: '#6366f1',
      category: '',
      is_active: true,
    });
    this.errorMessage.set(null);
  }
}
