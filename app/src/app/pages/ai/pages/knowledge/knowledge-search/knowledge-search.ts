import { ChangeDetectionStrategy, Component, DestroyRef, inject, signal } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { LucideAngularModule } from 'lucide-angular';
import {
  AfButtonComponent,
  AfEmptyStateComponent,
  AfPageTitleComponent,
  AfSearchInputComponent,
} from '@shared/components';
import { type KnowledgeSearchMode, type KnowledgeSearchResult } from '@ai/models/ai.model';
import { AiKnowledgeService } from '@ai/services/ai-knowledge.service';

/**
 * Busca semântica na base de conhecimento da IA.
 *
 * Contexto: realiza buscas via RAG com suporte a modo vetorial e híbrido.
 * Limita a 10 resultados com score mínimo de 0.3. Exibe estado vazio para busca sem resultados.
 */
@Component({
  selector: 'app-ai-knowledge-search',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfPageTitleComponent,
    AfSearchInputComponent,
    AfButtonComponent,
    AfEmptyStateComponent,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './knowledge-search.html',
})
export class KnowledgeSearchComponent {
  private readonly knowledgeService = inject(AiKnowledgeService);
  private readonly destroyRef = inject(DestroyRef);

  readonly semanticQuery = new FormControl('', { nonNullable: true });
  readonly semanticResults = signal<KnowledgeSearchResult[]>([]);
  readonly semanticLoading = signal(false);
  readonly semanticSearched = signal(false);
  readonly searchMode = signal<KnowledgeSearchMode>('vector');

  /** Executa a busca semântica com o termo atual do formulário. */
  runSemanticSearch(): void {
    const query = this.semanticQuery.value.trim();

    if (!query) {
      this.semanticResults.set([]);
      this.semanticSearched.set(true);
      return;
    }

    this.semanticLoading.set(true);
    this.semanticSearched.set(true);

    this.knowledgeService
      .search(query, 10, 0.3, this.searchMode())
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (results) => {
          this.semanticResults.set(results);
          this.semanticLoading.set(false);
        },
        error: () => {
          this.semanticResults.set([]);
          this.semanticLoading.set(false);
        },
      });
  }
}
