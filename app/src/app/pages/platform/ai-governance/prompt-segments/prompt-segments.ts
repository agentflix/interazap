import {
  type OnInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  inject,
  signal,
  viewChild,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
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
  AfStatusBadgeComponent,
  AfTableActionsComponent,
  type AfSelectOption,
} from '@shared/components';
import { type SegmentPrompt } from '@ai/models/ai.model';
import { AiGovernanceService } from '@core/services/ai-governance.service';
import { PromptSegmentFormComponent } from './components/prompt-segment-form/prompt-segment-form';

/**
 * List and CRUD page for segment prompts.
 */
@Component({
  selector: 'app-prompt-segments',
  standalone: true,
  imports: [
    LucideAngularModule,
    AfCrudPageComponent,
    AfDataTableComponent,
    AfModalComponent,
    AfConfirmModalComponent,
    AfButtonComponent,
    AfIconButtonComponent,
    AfLoadingButtonComponent,
    AfStatusBadgeComponent,
    AfTableActionsComponent,
    AfAlertComponent,
    PromptSegmentFormComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './prompt-segments.html',
})
export class PromptSegments implements OnInit {
  private readonly governanceService = inject(AiGovernanceService);
  private readonly destroyRef = inject(DestroyRef);

  readonly segmentFormRef = viewChild<PromptSegmentFormComponent>('segmentForm');

  readonly segments = signal<SegmentPrompt[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.segments().length === 0,
  );

  readonly masterOptions = signal<AfSelectOption[]>([]);

  readonly showFormModal = signal(false);
  readonly selectedSegment = signal<SegmentPrompt | null>(null);
  readonly formModalTitle = computed(() =>
    this.selectedSegment() ? 'Editar segmento' : 'Novo segmento',
  );

  readonly showDeleteModal = signal(false);
  readonly segmentToDelete = signal<SegmentPrompt | null>(null);
  readonly isDeleting = signal(false);
  readonly deleteMessage = computed(() => {
    const item = this.segmentToDelete();
    return item
      ? `Tem certeza que deseja desativar o segmento "${item.name}"?`
      : 'Tem certeza que deseja desativar este segmento?';
  });

  readonly showDeleted = signal(false);

  private searchTerm = signal('');
  private pageNumber = signal(1);

  ngOnInit(): void {
    this.loadSegments();
    this.loadMasterOptions();
  }

  openCreate(): void {
    this.selectedSegment.set(null);
    this.showFormModal.set(true);
  }

  openEdit(segment: SegmentPrompt): void {
    this.selectedSegment.set(segment);
    this.showFormModal.set(true);
  }

  openDelete(segment: SegmentPrompt): void {
    this.segmentToDelete.set(segment);
    this.showDeleteModal.set(true);
  }

  handleFormSaved(_item: SegmentPrompt): void {
    this.showFormModal.set(false);
    this.selectedSegment.set(null);
    this.loadSegments();
  }

  handleFormCancelled(): void {
    this.showFormModal.set(false);
    this.selectedSegment.set(null);
  }

  handleDeleteConfirmed(): void {
    const target = this.segmentToDelete();
    if (!target || this.isDeleting()) return;

    this.isDeleting.set(true);
    this.governanceService
      .deleteSegment(target.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isDeleting.set(false);
          this.showDeleteModal.set(false);
          this.segmentToDelete.set(null);
          this.loadSegments();
        },
        error: () => {
          this.isDeleting.set(false);
          this.showDeleteModal.set(false);
        },
      });
  }

  onSearch(term: string): void {
    this.searchTerm.set(term);
    this.pageNumber.set(1);
    this.loadSegments();
  }

  loadPage(page: number): void {
    this.pageNumber.set(page);
    this.loadSegments();
  }

  retry(): void {
    this.loadSegments();
  }

  toggleShowDeleted(): void {
    this.showDeleted.set(!this.showDeleted());
    this.pageNumber.set(1);
    this.loadSegments();
  }

  handleRestore(segment: SegmentPrompt): void {
    this.governanceService
      .restoreSegment(segment.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.loadSegments();
        },
        error: () => {},
      });
  }

  private loadSegments(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    this.governanceService
      .listSegments({ search: this.searchTerm(), withTrashed: this.showDeleted() })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.segments.set(response.data);
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

  private loadMasterOptions(): void {
    this.governanceService
      .listMasters()
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.masterOptions.set(
            response.data.map((master) => ({ value: master.id, label: master.name })),
          );
        },
        error: () => {
          this.masterOptions.set([]);
        },
      });
  }
}

export default PromptSegments;
