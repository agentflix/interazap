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
  AfConfirmModalComponent,
  AfCrudPageComponent,
  AfDataTableComponent,
  AfIconButtonComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfTextareaInputComponent,
} from '@shared/components';
import { type QuarantinedPrompt } from '@ai/models/ai.model';
import { AiGovernanceService } from '@core/services/ai-governance.service';

/**
 * Quarantine review page for prompts awaiting approval or rejection.
 */
@Component({
  selector: 'app-prompt-quarantine',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfCrudPageComponent,
    AfDataTableComponent,
    AfModalComponent,
    AfConfirmModalComponent,
    AfButtonComponent,
    AfIconButtonComponent,
    AfLoadingButtonComponent,
    AfTextareaInputComponent,
    AfAlertComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './prompt-quarantine.html',
})
export class PromptQuarantine implements OnInit {
  private readonly governanceService = inject(AiGovernanceService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly fb = inject(FormBuilder);

  readonly quarantineItems = signal<QuarantinedPrompt[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.quarantineItems().length === 0,
  );

  readonly approveModalOpen = signal(false);
  readonly rejectModalOpen = signal(false);
  readonly selectedItem = signal<QuarantinedPrompt | null>(null);
  readonly isSaving = signal(false);

  readonly rejectForm = this.fb.nonNullable.group({
    reason: ['', [Validators.required, Validators.minLength(3)]],
  });

  private searchTerm = signal('');
  private pageNumber = signal(1);

  ngOnInit(): void {
    this.loadQuarantine();
  }

  openApprove(item: QuarantinedPrompt): void {
    this.selectedItem.set(item);
    this.approveModalOpen.set(true);
  }

  closeApprove(): void {
    this.approveModalOpen.set(false);
    this.selectedItem.set(null);
  }

  confirmApprove(): void {
    const current = this.selectedItem();
    if (!current) return;

    this.isSaving.set(true);
    this.governanceService
      .approvePrompt(current.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isSaving.set(false);
          this.closeApprove();
          this.loadQuarantine();
        },
        error: () => {
          this.isSaving.set(false);
        },
      });
  }

  openReject(item: QuarantinedPrompt): void {
    this.selectedItem.set(item);
    this.rejectForm.reset({ reason: '' });
    this.rejectModalOpen.set(true);
  }

  closeReject(): void {
    this.rejectModalOpen.set(false);
    this.selectedItem.set(null);
  }

  confirmReject(): void {
    if (this.rejectForm.invalid || !this.selectedItem()) {
      this.rejectForm.markAllAsTouched();
      return;
    }

    this.isSaving.set(true);

    this.governanceService
      .rejectPrompt(this.selectedItem()!.id, this.rejectForm.controls.reason.value)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isSaving.set(false);
          this.closeReject();
          this.loadQuarantine();
        },
        error: () => {
          this.isSaving.set(false);
        },
      });
  }

  onSearch(term: string): void {
    this.searchTerm.set(term);
    this.pageNumber.set(1);
    this.loadQuarantine();
  }

  loadPage(page: number): void {
    this.pageNumber.set(page);
    this.loadQuarantine();
  }

  retry(): void {
    this.loadQuarantine();
  }

  private loadQuarantine(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    this.governanceService
      .listQuarantine({ search: this.searchTerm() })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.quarantineItems.set(response.data);
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

export default PromptQuarantine;
