import { Component, ChangeDetectionStrategy, input, output, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Navega entre páginas de um conjunto de dados paginado.
 *
 * Segue o contrato de PaginationMeta do Laravel:
 * `{ current_page, last_page, per_page, total }`.
 *
 * @example
 * ```html
 * <af-pagination
 *   [currentPage]="meta.current_page"
 *   [lastPage]="meta.last_page"
 *   [perPage]="meta.per_page"
 *   [total]="meta.total"
 *   (pageChange)="onPage($event)"
 * />
 * ```
 */
@Component({
  selector: 'af-pagination',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './pagination.html',
})
export class AfPaginationComponent {
  /** Página ativa atual (índice a partir de 1) */
  readonly currentPage = input.required<number>();

  /** Última página disponível */
  readonly lastPage = input.required<number>();

  /** Itens por página */
  readonly perPage = input(15);

  /** Total de itens */
  readonly total = input(0);

  /** Emitido quando o usuário navega para uma nova página */
  readonly pageChange = output<number>();

  /** Índice do primeiro item na página atual */
  protected readonly rangeStart = computed(() => {
    if (this.total() === 0) return 0;
    return (this.currentPage() - 1) * this.perPage() + 1;
  });

  /** Índice do último item na página atual */
  protected readonly rangeEnd = computed(() =>
    Math.min(this.currentPage() * this.perPage(), this.total()),
  );

  /** Array de números de página a renderizar, com -1 como sentinela de reticências */
  protected readonly visiblePages = computed(() => {
    const last = this.lastPage();
    const current = this.currentPage();

    if (last <= 7) {
      return Array.from({ length: last }, (_, i) => i + 1);
    }

    const pages: number[] = [1];

    if (current > 3) pages.push(-1);

    const start = Math.max(2, current - 1);
    const end = Math.min(last - 1, current + 1);

    for (let i = start; i <= end; i++) {
      pages.push(i);
    }

    if (current < last - 2) pages.push(-1);

    pages.push(last);

    return pages;
  });

  protected readonly activePageClass =
    'inline-flex items-center justify-center size-9 rounded-md text-sm font-semibold bg-accent-500 text-white cursor-default';

  protected readonly inactivePageClass =
    'inline-flex items-center justify-center size-9 rounded-md text-sm font-medium border border-neutral-300 dark:border-neutral-600 text-neutral-600 dark:text-neutral-400 hover:bg-neutral-100 dark:hover:bg-neutral-800 transition-colors cursor-pointer';

  protected goTo(page: number): void {
    if (page >= 1 && page <= this.lastPage() && page !== this.currentPage()) {
      this.pageChange.emit(page);
    }
  }
}
