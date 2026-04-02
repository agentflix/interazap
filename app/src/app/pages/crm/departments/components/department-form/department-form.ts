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
  AfSwitchInputComponent,
  AfTextInputComponent,
  AfTextareaInputComponent,
} from '@shared/components';
import { type Department, DepartmentService } from '@core/services/department.service';

/**
 * Department form component for creating and editing CRM departments.
 * Business logic preserved verbatim from source. Visual layer migrated to UI Kit.
 */
@Component({
  selector: 'app-department-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfTextInputComponent,
    AfTextareaInputComponent,
    AfSwitchInputComponent,
    AfAlertComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './department-form.html',
})
export class DepartmentFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly departmentService = inject(DepartmentService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly lastLoadedId = signal<string | null>(null);

  /** Department to edit — null for create mode */
  readonly department = input<Department | null>(null);

  /** Emitted after a successful save */
  readonly saved = output<Department>();

  /** Emitted when user cancels */
  readonly cancelled = output<void>();

  /** Save loading state — accessed by parent via viewChild */
  readonly isSaving = signal(false);

  /** Inline error message */
  readonly errorMessage = signal<string | null>(null);

  readonly form = this.fb.group({
    name: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    description: this.fb.control('', { nonNullable: true }),
    is_active: this.fb.control(true, { nonNullable: true }),
  });

  constructor() {
    effect(() => {
      const item = this.department();
      if (item) {
        if (this.lastLoadedId() === item.id) return;
        this.lastLoadedId.set(item.id);
        this.form.reset({
          name: item.name,
          description: item.description ?? '',
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
      description: formValue.description || undefined,
      is_active: formValue.is_active,
    };

    const editing = this.department();
    const request = editing
      ? this.departmentService.update(editing.id, payload)
      : this.departmentService.create(payload);

    this.isSaving.set(true);
    this.errorMessage.set(null);

    request.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        this.isSaving.set(false);
        this.saved.emit(response.data);
      },
      error: () => {
        this.isSaving.set(false);
        this.errorMessage.set('Não foi possível salvar o departamento. Tente novamente.');
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
      description: '',
      is_active: true,
    });
    this.errorMessage.set(null);
  }
}
