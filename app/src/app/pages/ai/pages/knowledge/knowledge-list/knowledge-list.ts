import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  computed,
  effect,
  inject,
  signal,
  viewChild,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute, Router } from '@angular/router';
import { LucideAngularModule } from 'lucide-angular';
import { interval } from 'rxjs';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfCheckboxInputComponent,
  AfConfirmModalComponent,
  AfCrudPageComponent,
  AfDataTableComponent,
  AfIconButtonComponent,
  AfLoadingButtonComponent,
  AfModalComponent,
  AfScrollAreaComponent,
  AfStatusBadgeComponent,
  AfTableActionsComponent,
} from '@shared/components';
import { RealtimeService } from '@core/services/realtime.service';
import { ToastService } from '@core/services/toast.service';
import { type AiKnowledge } from '@ai/models/ai.model';
import { AiKnowledgeService } from '@ai/services/ai-knowledge.service';
import { KnowledgeUploadComponent } from '../knowledge-upload/knowledge-upload';

/**
 * List and manage AI knowledge base documents.
 * Business logic preserved verbatim from source. Visual layer migrated to UI Kit.
 */
@Component({
  selector: 'app-ai-knowledge-list',
  standalone: true,
  imports: [
    LucideAngularModule,
    ReactiveFormsModule,
    AfCrudPageComponent,
    AfDataTableComponent,
    AfButtonComponent,
    AfCheckboxInputComponent,
    AfConfirmModalComponent,
    AfIconButtonComponent,
    AfLoadingButtonComponent,
    AfAlertComponent,
    AfModalComponent,
    AfScrollAreaComponent,
    AfStatusBadgeComponent,
    AfTableActionsComponent,
    KnowledgeUploadComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './knowledge-list.html',
})
export class KnowledgeListComponent {
  private readonly knowledgeService = inject(AiKnowledgeService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly toast = inject(ToastService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly realtime = inject(RealtimeService);

  readonly knowledgeUploadFormRef = viewChild<KnowledgeUploadComponent>('knowledgeUploadForm');
  readonly isUploadSaving = computed(() => this.knowledgeUploadFormRef()?.isUploading() ?? false);

  readonly documents = signal<AiKnowledge[]>([]);
  readonly isLoading = signal(true);
  readonly hasError = signal(false);
  readonly pollingActive = signal(false);
  readonly showUploadModal = signal(false);
  readonly isDetailsOpen = signal(false);
  readonly isDetailsLoading = signal(false);
  readonly detailsError = signal(false);
  readonly selectedDocument = signal<AiKnowledge | null>(null);
  readonly selectedIds = signal<string[]>([]);
  readonly meta = signal({ current_page: 1, last_page: 1, per_page: 10, total: 0 });

  // ─── Delete modal state ───────────────────────────────────────────────────
  readonly showDeleteModal = signal(false);
  readonly documentToDelete = signal<AiKnowledge | null>(null);
  readonly isDeleting = signal(false);

  readonly deleteModalTitle = computed(() =>
    this.documentToDelete() ? 'Excluir documento' : 'Excluir documentos',
  );
  readonly deleteConfirmLabel = computed(() =>
    this.documentToDelete() ? 'Excluir' : 'Excluir selecionados',
  );
  readonly deleteMessage = computed(() => {
    const doc = this.documentToDelete();
    if (!doc && this.selectedCount() > 0) {
      return `Tem certeza que deseja excluir ${this.selectedCount()} documentos selecionados? Esta ação não pode ser desfeita.`;
    }
    return doc
      ? `Tem certeza que deseja excluir o documento "${doc.title}"? Esta ação não pode ser desfeita.`
      : 'Tem certeza que deseja excluir este documento?';
  });

  // ─── Selection controls ──────────────────────────────────────────────────
  readonly selectAllControl = new FormControl<boolean>(false, { nonNullable: true });
  private readonly rowSelectionControls = new Map<string, FormControl<boolean>>();
  readonly selectedCount = computed(() => this.selectedIds().length);
  readonly hasSelection = computed(() => this.selectedCount() > 0);
  readonly websocketConnected = this.realtime.connected;
  readonly hasProcessingDocuments = computed(() =>
    this.documents().some(
      (document) => document.status === 'pending' || document.status === 'processing',
    ),
  );
  readonly shouldPoll = computed(() => this.hasProcessingDocuments() && !this.websocketConnected());

  readonly isEmpty = () => !this.isLoading() && !this.hasError() && this.documents().length === 0;

  private currentPage = 1;
  private currentSearch = '';

  constructor() {
    this.realtime.connect();
    this.realtime
      .on('ai.knowledge.document.processed')
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe(() => {
        this.fetchDocuments(this.currentPage);
      });

    effect((onCleanup) => {
      if (!this.shouldPoll()) {
        this.pollingActive.set(false);
        return;
      }

      this.pollingActive.set(true);

      const pollingSubscription = interval(5000)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe(() => {
          this.fetchDocuments(this.currentPage);
        });

      onCleanup(() => {
        pollingSubscription.unsubscribe();
      });
    });

    this.fetchDocuments(1);

    if (this.route.snapshot.queryParamMap.get('create') === '1') {
      this.showUploadModal.set(true);
      void this.router.navigate([], {
        relativeTo: this.route,
        queryParams: { create: null },
        queryParamsHandling: 'merge',
        replaceUrl: true,
      });
    }

    // Sync select all control with row selections
    this.selectAllControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((checked) => {
        const next = checked ? this.documents().map((d) => d.id) : [];
        this.selectedIds.set(next);
        for (const d of this.documents()) {
          this.getRowSelectionControl(d.id).setValue(checked);
        }
      });
  }

  onSearch(term: string): void {
    this.currentSearch = term.trim();
    this.fetchDocuments(1);
  }

  loadPage(page: number): void {
    this.fetchDocuments(page);
  }

  retry(): void {
    this.fetchDocuments(this.currentPage);
  }

  openUpload(): void {
    this.showUploadModal.set(true);
  }

  closeUploadModal(): void {
    this.showUploadModal.set(false);
  }

  handleUploadSaved(): void {
    this.showUploadModal.set(false);
    this.fetchDocuments(this.currentPage);
  }

  bulkReindexSelected(): void {
    const ids = this.selectedIds();
    if (ids.length === 0) {
      return;
    }

    this.knowledgeService
      .bulkReindex(ids)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.toast.success('Reindexação em lote iniciada.');
          this.clearSelection();
          this.fetchDocuments(this.currentPage);
        },
        error: () => this.toast.error('Erro ao reindexar documentos em lote.'),
      });
  }

  // ─── Selection control ────────────────────────────────────────────────────
  getRowSelectionControl(id: string): FormControl<boolean> {
    const existing = this.rowSelectionControls.get(id);
    if (existing) return existing;

    const control = new FormControl<boolean>(false, { nonNullable: true });
    control.valueChanges.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((checked) => {
      const selected = new Set(this.selectedIds());
      if (checked) selected.add(id);
      else selected.delete(id);
      this.selectedIds.set([...selected]);

      const allSelected =
        this.documents().length > 0 && this.documents().every((d) => selected.has(d.id));
      this.selectAllControl.setValue(allSelected, { emitEvent: false });
    });

    this.rowSelectionControls.set(id, control);
    return control;
  }

  private syncSelectionControls(documents: AiKnowledge[]): void {
    const activeIds = new Set(documents.map((d) => d.id));
    for (const key of this.rowSelectionControls.keys()) {
      if (!activeIds.has(key)) this.rowSelectionControls.delete(key);
    }
    const selected = this.selectedIds().filter((id) => activeIds.has(id));
    this.selectedIds.set(selected);
    for (const d of documents) {
      this.getRowSelectionControl(d.id).setValue(selected.includes(d.id));
    }
    this.selectAllControl.setValue(documents.length > 0 && selected.length === documents.length, {
      emitEvent: false,
    });
  }

  private clearSelection(): void {
    this.selectedIds.set([]);
    this.selectAllControl.setValue(false, { emitEvent: false });
    for (const control of this.rowSelectionControls.values()) {
      control.setValue(false, { emitEvent: false });
    }
  }

  openDetails(document: AiKnowledge): void {
    this.selectedDocument.set(document);
    this.isDetailsOpen.set(true);
    this.loadDetails(document.id);
  }

  closeDetails(): void {
    this.isDetailsOpen.set(false);
  }

  retryDetails(): void {
    const document = this.selectedDocument();
    if (!document) {
      return;
    }

    this.loadDetails(document.id);
  }

  goToDetailPage(id: string): void {
    this.closeDetails();
    void this.router.navigate(['/ai/knowledge', id]);
  }

  // ─── Delete ────────────────────────────────────────────────────────────────
  openDelete(doc: AiKnowledge): void {
    this.documentToDelete.set(doc);
    this.showDeleteModal.set(true);
  }

  openBulkDelete(): void {
    if (!this.hasSelection()) return;
    this.documentToDelete.set(null);
    this.showDeleteModal.set(true);
  }

  handleDeleteConfirmed(): void {
    if (this.isDeleting()) return;
    const single = this.documentToDelete();
    const ids = single ? [single.id] : this.selectedIds();
    if (ids.length === 0) return;

    this.isDeleting.set(true);

    const onSuccess = () => {
      this.isDeleting.set(false);
      this.showDeleteModal.set(false);
      this.documentToDelete.set(null);
      this.clearSelection();
      this.toast.success(
        ids.length > 1 ? 'Documentos excluídos com sucesso.' : 'Documento excluído com sucesso.',
      );
      this.fetchDocuments(this.currentPage);
    };

    const onError = () => {
      this.isDeleting.set(false);
      this.showDeleteModal.set(false);
    };

    if (single) {
      this.knowledgeService
        .delete(single.id)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe({ next: onSuccess, error: onError });
    } else {
      this.knowledgeService
        .bulkDelete(ids)
        .pipe(takeUntilDestroyed(this.destroyRef))
        .subscribe({ next: onSuccess, error: onError });
    }
  }

  /**
   * Reindex a document.
   */
  reindex(doc: AiKnowledge): void {
    this.knowledgeService
      .reindex(doc.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => {
          this.toast.success('Reindexação iniciada.');
          this.fetchDocuments(this.currentPage);
        },
        error: () => this.toast.error('Erro ao reindexar documento.'),
      });
  }

  /**
   * Get icon name for content type.
   */
  getTypeIcon(type: AiKnowledge['content_type']): string {
    const icons: Record<AiKnowledge['content_type'], string> = {
      text: 'file-text',
      pdf: 'file',
      url: 'link',
      csv: 'table',
    };
    return icons[type] ?? 'file-text';
  }

  /**
   * Format status label.
   */
  formatStatus(status: AiKnowledge['status']): string {
    const labels: Record<AiKnowledge['status'], string> = {
      pending: 'Pendente',
      processing: 'Processando',
      indexed: 'Indexado',
      failed: 'Falhou',
    };
    return labels[status] ?? status;
  }

  /**
   * Map knowledge document status to AfStatusBadgeComponent status type.
   */
  mapStatusToBadge(
    status: AiKnowledge['status'],
  ): 'online' | 'offline' | 'warning' | 'error' | 'idle' {
    const map: Record<AiKnowledge['status'], 'online' | 'offline' | 'warning' | 'error' | 'idle'> =
      {
        pending: 'warning',
        processing: 'idle',
        indexed: 'online',
        failed: 'error',
      };
    return map[status] ?? 'offline';
  }

  /**
   * Format token count.
   */
  formatTokens(count: number): string {
    if (count >= 1000) {
      return `${(count / 1000).toFixed(1)}K`;
    }
    return String(count);
  }

  formatDate(value?: string): string {
    if (!value) {
      return '—';
    }

    const parsedDate = new Date(value);
    if (Number.isNaN(parsedDate.getTime())) {
      return '—';
    }

    return parsedDate.toLocaleString('pt-BR');
  }

  private fetchDocuments(page: number): void {
    this.currentPage = page;
    this.isLoading.set(true);
    this.hasError.set(false);

    this.knowledgeService
      .list({
        page,
        per_page: this.meta().per_page,
        search: this.currentSearch || undefined,
      })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const documents = response.data ?? [];
          this.documents.set(documents);
          this.syncSelectionControls(documents);
          this.meta.set(response.meta);
          this.isLoading.set(false);
        },
        error: () => {
          this.hasError.set(true);
          this.isLoading.set(false);
        },
      });
  }

  private loadDetails(id: string): void {
    this.isDetailsLoading.set(true);
    this.detailsError.set(false);

    this.knowledgeService
      .get(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (document) => {
          this.selectedDocument.set(document);
          this.isDetailsLoading.set(false);
        },
        error: () => {
          this.detailsError.set(true);
          this.isDetailsLoading.set(false);
        },
      });
  }
}
