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
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfColorInputComponent,
  AfConfirmModalComponent,
  AfIconButtonComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfSwitchInputComponent,
  AfTextInputComponent,
  AfTextareaInputComponent,
} from '@shared/components';
import { FunnelService } from '@core/services/crm-funnel.service';
import type {
  Funnel,
  FunnelPayload,
  FunnelStep,
  FunnelStepPayload,
} from '@core/models/funnel.model';

/**
 * Formulário de criação e edição de funis com gerenciamento de etapas aninhadas.
 * Etapas podem ser reordenadas via drag-and-drop (HTML5 nativo).
 * Para novos funis, as etapas são armazenadas localmente e enviadas junto ao payload de criação.
 * Para funis existentes, as etapas são gerenciadas via API.
 */
@Component({
  selector: 'app-funnel-form',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfAlertComponent,
    AfButtonComponent,
    AfColorInputComponent,
    AfConfirmModalComponent,
    AfIconButtonComponent,
    AfLoadingButtonComponent,
    AfModalComponent,
    AfSwitchInputComponent,
    AfTextInputComponent,
    AfTextareaInputComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './crm-funnel-form.html',
})
export class FunnelFormComponent {
  private readonly fb = inject(FormBuilder);
  private readonly funnelService = inject(FunnelService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly lastLoadedId = signal<string | null>(null);

  readonly funnel = input<Funnel | null>(null);
  readonly saved = output<Funnel>();
  readonly cancelled = output<void>();

  readonly isSaving = signal(false);
  readonly errorMessage = signal<string | null>(null);

  readonly steps = signal<FunnelStep[]>([]);
  readonly isLoadingSteps = signal(false);

  // ─── Step modal ────────────────────────────────────────────────────────────
  readonly isStepModalOpen = signal(false);
  readonly editingStep = signal<FunnelStep | null>(null);
  readonly isSavingStep = signal(false);

  readonly isDeleteStepOpen = signal(false);
  readonly deletingStep = signal<FunnelStep | null>(null);
  readonly isDeletingStep = signal(false);

  // ─── Drag state ────────────────────────────────────────────────────────────
  readonly dragIndex = signal<number | null>(null);

  readonly form = this.fb.group({
    name: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    description: this.fb.control('', { nonNullable: true }),
    is_active: this.fb.control(true, { nonNullable: true }),
  });

  readonly stepForm = this.fb.group({
    name: this.fb.control('', { nonNullable: true, validators: [Validators.required] }),
    color: this.fb.control('#3b82f6', { nonNullable: true }),
    is_active: this.fb.control(true, { nonNullable: true }),
  });

  constructor() {
    effect(() => {
      const item = this.funnel();
      if (item) {
        const itemId = String(item.id);
        if (this.lastLoadedId() === itemId) return;
        this.lastLoadedId.set(itemId);
        this.form.reset({
          name: item.name,
          description: item.description ?? '',
          is_active: item.is_active,
        });
        this.loadSteps(item.id);
      } else {
        this.lastLoadedId.set(null);
        this.resetForm();
        this.steps.set([]);
      }
    });
  }

  submit(): void {
    if (this.form.invalid || this.isSaving()) {
      this.form.markAllAsTouched();
      return;
    }

    if (this.steps().length === 0) {
      this.errorMessage.set('Um funil precisa ter ao menos uma etapa.');
      return;
    }

    const fv = this.form.getRawValue();
    const payload: FunnelPayload = {
      name: fv.name,
      description: fv.description || undefined,
      is_active: fv.is_active,
    };

    const editing = this.funnel();
    if (!editing) {
      payload.steps = this.steps().map((step, index) => ({
        name: step.name,
        color: step.color ?? undefined,
        is_active: step.is_active ?? true,
        order: index + 1,
      }));
    }

    const request$ = editing
      ? this.funnelService.update(editing.id, payload)
      : this.funnelService.create(payload);

    this.isSaving.set(true);
    this.errorMessage.set(null);

    request$.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: (response) => {
        this.isSaving.set(false);
        this.resetForm();
        this.saved.emit(response.data);
      },
      error: () => {
        this.isSaving.set(false);
        this.errorMessage.set('Não foi possível salvar o funil. Tente novamente.');
      },
    });
  }

  cancel(): void {
    this.cancelled.emit();
  }

  // ─── Steps CRUD ────────────────────────────────────────────────────────────
  loadSteps(funnelId: string | number): void {
    this.isLoadingSteps.set(true);
    this.funnelService
      .listSteps(funnelId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.steps.set(response.data.steps.sort((a, b) => a.order - b.order));
          this.isLoadingSteps.set(false);
        },
        error: () => this.isLoadingSteps.set(false),
      });
  }

  openCreateStep(): void {
    this.stepForm.reset({ color: '#3b82f6', is_active: true });
    this.editingStep.set(null);
    this.isStepModalOpen.set(true);
  }

  openEditStep(step: FunnelStep): void {
    this.editingStep.set(step);
    this.stepForm.patchValue({
      name: step.name,
      color: step.color || '#3b82f6',
      is_active: step.is_active ?? true,
    });
    this.isStepModalOpen.set(true);
  }

  closeStepModal(): void {
    this.isStepModalOpen.set(false);
    this.editingStep.set(null);
  }

  submitStep(): void {
    if (this.stepForm.invalid) {
      this.stepForm.markAllAsTouched();
      return;
    }

    const fv = this.stepForm.getRawValue();
    const payload: FunnelStepPayload = {
      name: fv.name,
      color: fv.color || undefined,
      is_active: fv.is_active,
    };

    const funnel = this.funnel();

    // Local-only mode — no funnel saved yet
    if (!funnel) {
      if (this.editingStep()) {
        this.steps.update((steps) =>
          steps.map((s) =>
            s.id === this.editingStep()!.id ? ({ ...s, ...payload } as FunnelStep) : s,
          ),
        );
      } else {
        const newStep: FunnelStep = {
          id: String(Date.now()),
          funnel_id: '0',
          name: payload.name,
          color: payload.color ?? null,
          is_active: payload.is_active ?? true,
          order: this.steps().length + 1,
        };
        this.steps.update((steps) => [...steps, newStep]);
      }
      this.closeStepModal();
      return;
    }

    // API mode
    this.isSavingStep.set(true);
    const requestPayload: FunnelStepPayload = this.editingStep()
      ? payload
      : { ...payload, order: this.steps().length + 1 };

    const request$ = this.editingStep()
      ? this.funnelService.updateStep(funnel.id, this.editingStep()!.id, requestPayload)
      : this.funnelService.createStep(funnel.id, requestPayload);

    request$.pipe(takeUntilDestroyed(this.destroyRef)).subscribe({
      next: () => {
        this.isSavingStep.set(false);
        this.closeStepModal();
        this.loadSteps(funnel.id);
      },
      error: () => this.isSavingStep.set(false),
    });
  }

  confirmDeleteStep(step: FunnelStep): void {
    this.deletingStep.set(step);
    this.isDeleteStepOpen.set(true);
  }

  closeDeleteStep(): void {
    this.isDeleteStepOpen.set(false);
    this.deletingStep.set(null);
  }

  deleteStepItem(): void {
    const step = this.deletingStep();
    if (!step) return;

    const funnel = this.funnel();
    if (!funnel) {
      this.steps.update((steps) => steps.filter((s) => s.id !== step.id));
      this.closeDeleteStep();
      return;
    }

    this.isDeletingStep.set(true);
    this.funnelService
      .deleteStep(funnel.id, step.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isDeletingStep.set(false);
          this.closeDeleteStep();
          this.loadSteps(funnel.id);
        },
        error: () => this.isDeletingStep.set(false),
      });
  }

  // ─── Native HTML5 Drag & Drop ──────────────────────────────────────────────
  onDragStart(index: number): void {
    this.dragIndex.set(index);
  }

  onDragOver(event: DragEvent, _index: number): void {
    event.preventDefault();
  }

  onDrop(event: DragEvent, targetIndex: number): void {
    event.preventDefault();
    const sourceIndex = this.dragIndex();
    if (sourceIndex === null || sourceIndex === targetIndex) return;

    const currentSteps = [...this.steps()];
    const [moved] = currentSteps.splice(sourceIndex, 1);
    currentSteps.splice(targetIndex, 0, moved);

    currentSteps.forEach((step, i) => {
      step.order = i + 1;
    });

    this.steps.set(currentSteps);
    this.dragIndex.set(null);

    const funnel = this.funnel();
    if (!funnel) return;

    const stepIds = currentSteps.map((s) => s.id);
    this.funnelService
      .reorderSteps(funnel.id, stepIds)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => this.loadSteps(funnel.id),
        error: () => this.loadSteps(funnel.id),
      });
  }

  onDragEnd(): void {
    this.dragIndex.set(null);
  }

  private resetForm(): void {
    this.form.reset({ name: '', description: '', is_active: true });
    this.errorMessage.set(null);
  }
}
