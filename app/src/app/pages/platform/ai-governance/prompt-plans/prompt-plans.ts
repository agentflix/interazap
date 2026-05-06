import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfCrudPageComponent,
  AfDataTableComponent,
  AfIconButtonComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfTextareaInputComponent,
} from '@shared/components';
import { type PlanPrompt } from '@ai/models/ai.model';
import { AiGovernanceService } from '@core/services/ai-governance.service';

/**
 * Read and update page for plan prompts.
 * No create/delete — only inline editing via modal.
 */
@Component({
  selector: 'app-prompt-plans',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfCrudPageComponent,
    AfDataTableComponent,
    AfModalComponent,
    AfButtonComponent,
    AfIconButtonComponent,
    AfLoadingButtonComponent,
    AfTextareaInputComponent,
    AfAlertComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './prompt-plans.html',
})
export class PromptPlans implements OnInit {
  private readonly governanceService = inject(AiGovernanceService);
  private readonly fb = inject(FormBuilder);
  private readonly destroyRef = inject(DestroyRef);

  readonly plans = signal<PlanPrompt[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.plans().length === 0,
  );

  readonly editModalOpen = signal(false);
  readonly currentItem = signal<PlanPrompt | null>(null);
  readonly isSaving = signal(false);
  readonly errorMessage = signal<string | null>(null);

  readonly form = this.fb.nonNullable.group({
    content: ['', [Validators.required, Validators.minLength(10)]],
  });

  private searchTerm = signal('');
  private pageNumber = signal(1);

  ngOnInit(): void {
    this.loadPlans();
  }

  openEdit(item: PlanPrompt): void {
    this.errorMessage.set(null);
    this.currentItem.set(item);
    this.form.reset({
      content: item.content,
    });
    this.editModalOpen.set(true);
  }

  closeEdit(): void {
    this.editModalOpen.set(false);
    this.currentItem.set(null);
  }

  saveEdit(): void {
    const current = this.currentItem();

    if (!current || this.form.invalid) {
      this.form.markAllAsTouched();
      return;
    }

    this.isSaving.set(true);
    this.errorMessage.set(null);

    this.governanceService
      .updatePlan(current.plan_id, {
        content: this.form.controls.content.value,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isSaving.set(false);
          this.closeEdit();
          this.loadPlans();
        },
        error: () => {
          this.isSaving.set(false);
          this.errorMessage.set('Não foi possível salvar. Verifique os dados e tente novamente.');
        },
      });
  }

  onSearch(term: string): void {
    this.searchTerm.set(term);
    this.pageNumber.set(1);
    this.loadPlans();
  }

  loadPage(page: number): void {
    this.pageNumber.set(page);
    this.loadPlans();
  }

  retry(): void {
    this.loadPlans();
  }

  private loadPlans(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    this.governanceService
      .listPlans({ search: this.searchTerm() })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.plans.set(response.data);
          this.meta.set(
            response.meta ?? {
              current_page: 1,
              last_page: 1,
              per_page: 15,
              total: response.data.length,
            },
          );
          this.isLoading.set(false);
        },
        error: () => {
          this.isLoading.set(false);
          this.hasError.set(true);
        },
      });
  }
}

export default PromptPlans;
