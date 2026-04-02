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
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfConfirmModalComponent,
  AfCrudPageComponent,
  AfDataTableComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfSelectInputComponent,
  AfSortableHeaderComponent,
  AfStatusBadgeComponent,
  AfTableActionsComponent,
  type AfSelectOption,
  type SortDirection,
} from '@shared/components';
import { ToastService } from '@core/services/toast.service';
import { type QuickAnswer, QuickAnswerService } from '@core/services/quick-answer.service';
import { QuickAnswerFormComponent } from './components/quick-answer-form/quick-answer-form';

/**
 * Componente para gestão de respostas rápidas (atalhos de texto).
 *
 * Permite ao usuário cadastrar modelos de mensagens pré-definidas com atalhos,
 * facilitando o envio de informações comuns durante o atendimento.
 *
 * Lógica de negócio preservada do legado; camada visual migrada para UI Kit (af-*).
 */
@Component({
  selector: 'app-quick-answers',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfCrudPageComponent,
    AfModalComponent,
    AfConfirmModalComponent,
    AfButtonComponent,
    AfLoadingButtonComponent,
    AfSelectInputComponent,
    AfDataTableComponent,
    AfSortableHeaderComponent,
    AfStatusBadgeComponent,
    AfTableActionsComponent,
    AfAlertComponent,
    QuickAnswerFormComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './quick-answers.html',
})
export class QuickAnswers implements OnInit {
  private readonly quickAnswerService = inject(QuickAnswerService);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);

  readonly quickAnswerFormRef = viewChild<QuickAnswerFormComponent>('quickAnswerForm');
  readonly isFormSaving = computed(() => this.quickAnswerFormRef()?.isSaving() ?? false);

  // ─── List state ────────────────────────────────────────────────────────────
  readonly quickAnswers = signal<QuickAnswer[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 15, total: 0 });

  readonly searchTerm = signal('');
  private readonly pageNumber = signal(1);
  readonly sortBy = signal<string>('name');
  readonly sortDir = signal<SortDirection>('asc');

  readonly isEmpty = computed(
    () => !this.isLoading() && !this.hasError() && this.quickAnswers().length === 0,
  );

  // ─── Status filter ─────────────────────────────────────────────────────────
  readonly filterStatusControl = new FormControl<string>('all', { nonNullable: true });
  readonly filterStatusOptions: AfSelectOption[] = [
    { label: 'Todos', value: 'all' },
    { label: 'Ativo', value: 'active' },
    { label: 'Inativo', value: 'inactive' },
  ];

  // ─── Modal state ───────────────────────────────────────────────────────────
  readonly showFormModal = signal(false);
  readonly selectedItem = signal<QuickAnswer | null>(null);
  readonly formModalTitle = computed(() =>
    this.selectedItem() ? 'Editar resposta rápida' : 'Nova resposta rápida',
  );

  readonly showDeleteModal = signal(false);
  readonly itemToDelete = signal<QuickAnswer | null>(null);
  readonly isDeleting = signal(false);

  constructor() {
    this.filterStatusControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.pageNumber.set(1);
        this.loadItems();
      });
  }

  ngOnInit(): void {
    this.loadItems();
  }

  // ─── Actions ───────────────────────────────────────────────────────────────
  openCreate(): void {
    this.selectedItem.set(null);
    this.showFormModal.set(true);
  }

  openEdit(item: QuickAnswer): void {
    this.selectedItem.set(item);
    this.showFormModal.set(true);
  }

  openDelete(item: QuickAnswer): void {
    this.itemToDelete.set(item);
    this.showDeleteModal.set(true);
  }

  deleteMessage(): string {
    return `Tem certeza que deseja excluir a resposta rápida '${this.itemToDelete()?.name ?? ''}'? Esta ação não pode ser desfeita.`;
  }

  handleFormSaved(_item: QuickAnswer): void {
    this.showFormModal.set(false);
    this.selectedItem.set(null);
    this.toast.success('Resposta rápida salva com sucesso.');
    this.loadItems();
  }

  handleFormCancelled(): void {
    this.showFormModal.set(false);
    this.selectedItem.set(null);
  }

  handleDeleteConfirmed(): void {
    const item = this.itemToDelete();
    if (!item || this.isDeleting()) return;

    this.isDeleting.set(true);
    this.quickAnswerService
      .delete(item.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.isDeleting.set(false);
          this.showDeleteModal.set(false);
          this.itemToDelete.set(null);
          this.toast.success('Resposta rápida excluída com sucesso.');
          this.loadItems();
        },
        error: () => {
          this.isDeleting.set(false);
          this.toast.error('Não foi possível excluir a resposta rápida.');
        },
      });
  }

  onSearch(term: string): void {
    this.searchTerm.set(term);
    this.pageNumber.set(1);
    this.loadItems();
  }

  loadPage(page: number): void {
    this.pageNumber.set(page);
    this.loadItems();
  }

  onSort(event: { field: string; dir: SortDirection }): void {
    this.sortBy.set(event.field);
    this.sortDir.set(event.dir);
    this.pageNumber.set(1);
    this.loadItems();
  }

  retry(): void {
    this.loadItems();
  }

  // ─── Data loading ──────────────────────────────────────────────────────────
  private loadItems(): void {
    this.isLoading.set(true);
    this.hasError.set(false);

    const statusValue = this.filterStatusControl.value;
    const is_active =
      statusValue === 'active' ? true : statusValue === 'inactive' ? false : undefined;

    this.quickAnswerService
      .list({
        search: this.searchTerm() || undefined,
        page: this.pageNumber(),
        per_page: 15,
        sort_by: this.sortBy(),
        sort_dir: this.sortDir(),
        is_active,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.quickAnswers.set(response.data);
          this.meta.set(response.meta);
          this.isLoading.set(false);
        },
        error: () => {
          this.isLoading.set(false);
          this.hasError.set(true);
        },
      });
  }
}
