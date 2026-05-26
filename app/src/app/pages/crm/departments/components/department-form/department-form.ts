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
import { DepartmentService } from '@core/services/department.service';
import type { Department } from '@core/models/department.model';

  /**
   * Evento emitido quando o formulário é cancelado.
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

  /**
   * Departamento para edição — null para modo de criação.
   */
  readonly department = input<Department | null>(null);

  /**
   * Evento emitido após salvar com sucesso.
   */
  readonly saved = output<Department>();

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
        this.resetForm();
        this.saved.emit(response.data);
      },
      error: () => {
        this.isSaving.set(false);
        this.errorMessage.set('Não foi possível salvar o departamento. Tente novamente.');
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
      description: '',
      is_active: true,
    });
    this.errorMessage.set(null);
  }
}
