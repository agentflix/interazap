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

/** Mapeamento de status de documento para variante de badge. */
const STATUS_BADGE_MAP: Record<
  AiKnowledge['status'],
  { badge: 'online' | 'warning' | 'idle' | 'error'; label: string }
> = {
  indexed: { badge: 'online', label: 'Pronto' },
  processing: { badge: 'idle', label: 'Processando' },
  pending: { badge: 'warning', label: 'Pendente' },
  failed: { badge: 'error', label: 'Falha' },
};

/** Mapeamento de tipo de conteúdo para nome de ícone Lucide. */
const TYPE_ICON_MAP: Record<AiKnowledge['content_type'], string> = {
  text: 'file-text',
  pdf: 'file',
  url: 'link',
  csv: 'table',
};

/**
 * Dashboard da Base de Conhecimento com estatísticas de armazenamento e documentos.
 *
 * Contexto: exibe cartões de estatísticas, barra de distribuição por status, documentos
 * recentes e ações rápidas (upload, busca). Carrega dados em paralelo via forkJoin.
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

  /** Média de chunks por documento (0 quando não há documentos). */
  readonly avgChunksPerDoc = computed(() => {
    const s = this.stats();
    if (!s || s.document_count === 0) return 0;
    return Math.round(s.total_chunks / s.document_count);
  });

  /** Distribuição de status com rótulo, contagem, percentual e cor. */
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

  /** Classe de cor dinâmica para a barra de progresso de armazenamento. */
  readonly storageBarColor = computed(() => {
    const pct = this.stats()?.storage_used_percent ?? 0;
    if (pct >= 90) return 'bg-danger';
    if (pct >= 70) return 'bg-warning';
    return 'bg-accent-500';
  });

  ngOnInit(): void {
    this.loadData();
  }

  /** Recarrega todos os dados do dashboard. */
  reload(): void {
    this.loadData();
  }

  /** Tenta recarregar apenas os documentos recentes. */
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

  /** Navega para a página de upload da base de conhecimento. */
  goToUpload(): void {
    void this.router.navigate(['/ai/knowledge/upload']);
  }

  /** Navega para a página de busca da base de conhecimento. */
  goToSearch(): void {
    void this.router.navigate(['/ai/knowledge/search']);
  }

  /** Retorna o nome do ícone Lucide para o tipo de conteúdo. */
  getTypeIcon(contentType: AiKnowledge['content_type']): string {
    return TYPE_ICON_MAP[contentType] ?? 'file-text';
  }

  /** Retorna a variante do badge para o status do documento. */
  getStatusBadge(status: AiKnowledge['status']): 'online' | 'warning' | 'idle' | 'error' {
    return STATUS_BADGE_MAP[status]?.badge ?? 'warning';
  }

  /** Retorna o rótulo de exibição para o status do documento. */
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
