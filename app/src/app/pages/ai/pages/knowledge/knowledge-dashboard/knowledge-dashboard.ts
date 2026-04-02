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
import { Router } from '@angular/router';
import { forkJoin } from 'rxjs';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfAlertComponent,
  AfButtonComponent,
  AfEmptyStateComponent,
  AfPageTitleComponent,
  AfStatusBadgeComponent,
} from '@shared/components';
import { AiKnowledgeService } from '@ai/services/ai-knowledge.service';
import { type AiKnowledge, type KnowledgeStats } from '@ai/models/ai.model';

/** Status-to-badge mapping for knowledge documents. */
const STATUS_BADGE_MAP: Record<
  AiKnowledge['status'],
  { badge: 'online' | 'warning' | 'idle' | 'error'; label: string }
> = {
  indexed: { badge: 'online', label: 'Pronto' },
  processing: { badge: 'idle', label: 'Processando' },
  pending: { badge: 'warning', label: 'Pendente' },
  failed: { badge: 'error', label: 'Falha' },
};

/** Content-type to lucide icon name mapping. */
const TYPE_ICON_MAP: Record<AiKnowledge['content_type'], string> = {
  text: 'file-text',
  pdf: 'file',
  url: 'link',
  csv: 'table',
};

/**
 * Knowledge base usage dashboard with storage and document stats.
 * Displays stat cards, status distribution bar, recent documents and quick actions.
 */
@Component({
  selector: 'app-ai-knowledge-dashboard',
  standalone: true,
  imports: [
    LucideAngularModule,
    AfPageTitleComponent,
    AfButtonComponent,
    AfAlertComponent,
    AfEmptyStateComponent,
    AfStatusBadgeComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './knowledge-dashboard.html',
})
export class KnowledgeDashboardComponent implements OnInit {
  private readonly knowledgeService = inject(AiKnowledgeService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly router = inject(Router);

  readonly stats = signal<KnowledgeStats | null>(null);
  readonly statsLoading = signal(true);
  readonly statsError = signal(false);
  readonly recentDocs = signal<AiKnowledge[]>([]);
  readonly recentDocsError = signal(false);

  /** Average chunks per document (0 when no documents). */
  readonly avgChunksPerDoc = computed(() => {
    const s = this.stats();
    if (!s || s.document_count === 0) return 0;
    return Math.round(s.total_chunks / s.document_count);
  });

  /** Status distribution with label, count, percentage and color. */
  readonly statusDistribution = computed(() => {
    const s = this.stats();
    if (!s || s.document_count === 0) return [];
    const total = s.document_count;
    return [
      {
        label: 'Prontos',
        count: s.documents_ready,
        pct: (s.documents_ready / total) * 100,
        color: 'bg-success',
      },
      {
        label: 'Processando',
        count: s.documents_processing,
        pct: (s.documents_processing / total) * 100,
        color: 'bg-accent-500',
      },
      {
        label: 'Pendentes',
        count: s.documents_pending,
        pct: (s.documents_pending / total) * 100,
        color: 'bg-warning',
      },
      {
        label: 'Com falha',
        count: s.documents_failed,
        pct: (s.documents_failed / total) * 100,
        color: 'bg-danger',
      },
    ];
  });

  /** Dynamic color class for the storage progress bar. */
  readonly storageBarColor = computed(() => {
    const pct = this.stats()?.storage_used_percent ?? 0;
    if (pct >= 90) return 'bg-danger';
    if (pct >= 70) return 'bg-warning';
    return 'bg-accent-500';
  });

  ngOnInit(): void {
    this.loadData();
  }

  /** Reload all dashboard data. */
  reload(): void {
    this.loadData();
  }

  /** Retry loading only recent documents. */
  retryRecentDocs(): void {
    this.recentDocsError.set(false);
    this.knowledgeService
      .list({ per_page: 5 })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (result) => this.recentDocs.set(result.data),
        error: () => this.recentDocsError.set(true),
      });
  }

  /** Navigate to the knowledge upload page. */
  goToUpload(): void {
    void this.router.navigate(['/ai/knowledge/upload']);
  }

  /** Navigate to the knowledge search page. */
  goToSearch(): void {
    void this.router.navigate(['/ai/knowledge/search']);
  }

  /** Get the lucide icon name for a content type. */
  getTypeIcon(contentType: AiKnowledge['content_type']): string {
    return TYPE_ICON_MAP[contentType] ?? 'file-text';
  }

  /** Get the status badge variant for a document status. */
  getStatusBadge(status: AiKnowledge['status']): 'online' | 'warning' | 'idle' | 'error' {
    return STATUS_BADGE_MAP[status]?.badge ?? 'warning';
  }

  /** Get the display label for a document status. */
  getStatusLabel(status: AiKnowledge['status']): string {
    return STATUS_BADGE_MAP[status]?.label ?? status;
  }

  private loadData(): void {
    this.statsLoading.set(true);
    this.statsError.set(false);
    this.recentDocsError.set(false);

    forkJoin({
      stats: this.knowledgeService.getStats(),
      recent: this.knowledgeService.list({ per_page: 5 }),
    })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (result) => {
          this.stats.set(result.stats);
          this.recentDocs.set(result.recent.data);
          this.statsLoading.set(false);
        },
        error: () => {
          this.statsError.set(true);
          this.statsLoading.set(false);
        },
      });
  }
}
