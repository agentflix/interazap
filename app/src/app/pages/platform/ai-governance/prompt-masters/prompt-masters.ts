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
} from '@shared/components';
import { type MasterPrompt } from '@ai/models/ai.model';
import { AiGovernanceService } from '@core/services/ai-governance.service';
import { PromptMasterFormComponent } from './components/prompt-master-form/prompt-master-form';

/**
 * List and CRUD page for master prompts.
 */
@Component({
  selector: 'app-prompt-masters',
  standalone: true,
  imports: [
    LucideAngularModule,
    AfCrudPageComponent,
    AfDataTableComponent,
    AfModalComponent,
    AfConfirmModalComponent,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfStatusBadgeComponent,
    AfAlertComponent,
    AfIconButtonComponent,
    PromptMasterFormComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './prompt-masters.html',
})
export class PromptMasters implements OnInit {
  private readonly governanceService = inject(AiGovernanceService);
  private readonly destroyRef = inject(DestroyRef);

  readonly masterFormRef = viewChild<PromptMasterFormComponent>('masterForm');

  readonly masters = signal<MasterPrompt[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.masters().length === 0,
  );

  readonly showFormModal = signal(false);
  readonly selectedMaster = signal<MasterPrompt | null>(null);
  readonly formModalTitle = computed(() =>
    this.selectedMaster() ? 'Editar master prompt' : 'Novo master prompt',
  );

  readonly showDeleteModal = signal(false);
  readonly masterToDelete = signal<MasterPrompt | null>(null);
  readonly isDeleting = signal(false);
  readonly deleteMessage = computed(() => {
    const item = this.masterToDelete();
    return item
      ? `Tem certeza que deseja desativar o master prompt "${item.name}"?`
      : 'Tem certeza que deseja desativar este master prompt?';
  });

  private searchTerm = signal('');
  private pageNumber = signal(1);

  ngOnInit(): void {
    this.loadMasters();
  }

  openCreate(): void {
    this.selectedMaster.set(null);
    this.showFormModal.set(true);
  }

  openEdit(master: MasterPrompt): void {
    this.selectedMaster.set(master);
    this.showFormModal.set(true);
  }

  openDelete(master: MasterPrompt): void {
    this.masterToDelete.set(master);
    this.showDeleteModal.set(true);
  }

  handleFormSaved(_item: MasterPrompt): void {
    this.showFormModal.set(false);
    this.selectedMaster.set(null);
    this.loadMasters();
  }

  handleFormCancelled(): void {
    this.showFormModal.set(false);
    this.selectedMaster.set(null);
  }

  handleDeleteConfirmed(): void {
    const target = this.masterToDelete();
    if (!target || this.isDeleting()) return;

    this.isDeleting.set(true);
    this.governanceService
      .deleteMaster(target.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isDeleting.set(false);
          this.showDeleteModal.set(false);
          this.masterToDelete.set(null);
          this.loadMasters();
        },
        error: () => {
          this.isDeleting.set(false);
          this.showDeleteModal.set(false);
        },
      });
  }

  handleToggle(master: MasterPrompt): void {
    this.governanceService
      .toggleMaster(master.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => this.loadMasters(),
        error: () => {},
      });
  }

  onSearch(term: string): void {
    this.searchTerm.set(term);
    this.pageNumber.set(1);
    this.loadMasters();
  }

  loadPage(page: number): void {
    this.pageNumber.set(page);
    this.loadMasters();
  }

  retry(): void {
    this.loadMasters();
  }

  private loadMasters(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    this.governanceService
      .listMasters({ search: this.searchTerm() })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.masters.set(response.data);
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

export default PromptMasters;
