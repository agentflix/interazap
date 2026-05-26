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
import { ActivatedRoute, Router } from '@angular/router';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfEmptyStateComponent,
  AfPageTitleComponent,
} from '@shared/components';
import { type AiKnowledge, type KnowledgeChunk } from '@ai/models/ai.model';
import { AiKnowledgeService } from '@ai/services/ai-knowledge.service';

/**
 * Detalhe de documento da base de conhecimento com listagem de chunks.
 *
 * Contexto: exibe metadados do documento e chunks paginados (20 por página).
 * Suporta reindexação e exclusão do documento. Navega de volta para /ai/knowledge/base.
 */
@Component({
  selector: 'app-knowledge-detail',
  standalone: true,
  imports: [
    LucideAngularModule,
    AfPageTitleComponent,
    AfButtonComponent,
    AfAlertComponent,
    AfEmptyStateComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './knowledge-detail.html',
})
export class KnowledgeDetailComponent implements OnInit {
  private readonly knowledgeService = inject(AiKnowledgeService);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);

  readonly document = signal<AiKnowledge | null>(null);
  readonly chunks = signal<KnowledgeChunk[]>([]);
  readonly loading = signal(true);
  readonly chunksLoading = signal(false);
  readonly error = signal(false);

  readonly currentPage = signal(1);
  readonly lastPage = signal(1);
  readonly totalChunks = signal(0);
  readonly expandedChunkId = signal<string | null>(null);

  readonly hasPreviousPage = computed(() => this.currentPage() > 1);
  readonly hasNextPage = computed(() => this.currentPage() < this.lastPage());

  ngOnInit(): void {
    const id = this.route.snapshot.paramMap.get('id');

    if (!id) {
      this.error.set(true);
      this.loading.set(false);

      return;
    }

    this.loadDocument(id);
    this.loadChunks(id, 1);
  }

  /** Navega de volta para a lista de documentos da base de conhecimento. */
  goBack(): void {
    void this.router.navigate(['/ai/knowledge/base']);
  }

  /** Inicia a reindexação do documento atual. */
  reindex(): void {
    const doc = this.document();
    if (!doc) {
      return;
    }

    this.knowledgeService
      .reindex(doc.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (updated) => this.document.set(updated),
      });
  }

  /** Exclui o documento atual e navega de volta para a lista. */
  removeDocument(): void {
    const doc = this.document();
    if (!doc) {
      return;
    }

    this.knowledgeService
      .delete(doc.id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: () => this.goBack(),
      });
  }

  /**
   * Expande ou recolhe a exibição de um chunk.
   * @param chunkId ID do chunk a alternar
   */
  toggleChunk(chunkId: string): void {
    this.expandedChunkId.set(this.expandedChunkId() === chunkId ? null : chunkId);
  }

  previousPage(): void {
    const doc = this.document();
    if (!doc || !this.hasPreviousPage()) {
      return;
    }

    this.loadChunks(doc.id, this.currentPage() - 1);
  }

  nextPage(): void {
    const doc = this.document();
    if (!doc || !this.hasNextPage()) {
      return;
    }

    this.loadChunks(doc.id, this.currentPage() + 1);
  }

  formatStatus(status: AiKnowledge['status']): string {
    const labels: Record<AiKnowledge['status'], string> = {
      pending: 'Pendente',
      processing: 'Processando',
      indexed: 'Indexado',
      failed: 'Falhou',
    };

    return labels[status] ?? status;
  }

  statusClass(status: AiKnowledge['status']): string {
    const classes: Record<AiKnowledge['status'], string> = {
      pending:
        'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-900/20 dark:text-amber-400 dark:border-amber-800',
      processing:
        'bg-sky-50 text-sky-700 border-sky-200 dark:bg-sky-900/20 dark:text-sky-400 dark:border-sky-800',
      indexed:
        'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-900/20 dark:text-emerald-400 dark:border-emerald-800',
      failed:
        'bg-red-50 text-red-700 border-red-200 dark:bg-red-900/20 dark:text-red-400 dark:border-red-800',
    };

    return classes[status] ?? 'bg-neutral-100 text-neutral-600 border-neutral-200';
  }

  private loadDocument(id: string): void {
    this.loading.set(true);
    this.error.set(false);

    this.knowledgeService
      .get(id)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (doc) => {
          this.document.set(doc);
          this.loading.set(false);
        },
        error: () => {
          this.error.set(true);
          this.loading.set(false);
        },
      });
  }

  private loadChunks(documentId: string, page: number): void {
    this.chunksLoading.set(true);

    this.knowledgeService
      .getChunks(documentId, page, 20)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          this.chunks.set(response.data ?? []);
          this.currentPage.set(response.meta.current_page);
          this.lastPage.set(response.meta.last_page);
          this.totalChunks.set(response.meta.total);
          this.chunksLoading.set(false);
        },
        error: () => {
          this.chunks.set([]);
          this.chunksLoading.set(false);
        },
      });
  }
}
