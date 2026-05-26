import { Component, ChangeDetectionStrategy, input, output, computed } from '@angular/core';
import { LucideAngularModule } from 'lucide-angular';
import { type SortDirection } from '../crud-page/models/index';

/**
 * Célula de cabeçalho de tabela reutilizável com ordenação por clique.
 *
 * Renderiza um `<th>` com clique para ordenar e indicador visual
 * da direção atual da ordenação. Projetado para uso dentro de
 * linhas `<thead>` do `af-data-table`.
 *
 * @example
 * ```html
 * <th
 *   afSortableHeader
 *   label="Nome"
 *   field="name"
 *   [currentField]="sortBy()"
 *   [currentDir]="sortDir()"
 *   (sortChange)="onSort($event)"
 * ></th>
 * ```
 */
@Component({
  // eslint-disable-next-line @angular-eslint/component-selector
  selector: '[afSortableHeader]',
  standalone: true,
  imports: [LucideAngularModule],
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    role: 'columnheader',
    '[attr.aria-sort]': 'ariaSort()',
    '[class]': 'hostClasses()',
  },
  templateUrl: './sortable-header.html',
})
export class AfSortableHeaderComponent {
  /** Rótulo de exibição da coluna */
  readonly label = input.required<string>();

  /** Nome do campo enviado à API */
  readonly field = input.required<string>();

  /** Campo de ordenação ativo atualmente */
  readonly currentField = input<string>('');

  /** Direção de ordenação ativa atualmente */
  readonly currentDir = input<SortDirection>('asc');

  /** Classes CSS extras para o `<th>` hospedeiro */
  readonly class = input<string>('');

  /** Emitido ao alternar ordenação — payload é `{ field, dir }` */
  readonly sortChange = output<{ field: string; dir: SortDirection }>();

  /** Indica se esta coluna é o alvo da ordenação ativa */
  protected readonly isActive = computed(() => this.currentField() === this.field());

  /** Classes do elemento hospedeiro */
  protected readonly hostClasses = computed(() => {
    const base = 'px-3.5 py-2 text-start text-sm font-medium';
    const extra = this.class();
    return extra ? `${base} ${extra}` : base;
  });

  /** Valor do atributo ARIA sort */
  protected readonly ariaSort = computed(() => {
    if (!this.isActive()) return 'none';
    return this.currentDir() === 'asc' ? 'ascending' : 'descending';
  });

  /** Destaque do ícone de seta para cima */
  protected readonly upIconClasses = computed(() =>
    this.isActive() && this.currentDir() === 'asc'
      ? 'text-primary'
      : 'text-neutral-300 dark:text-neutral-600',
  );

  /** Destaque do ícone de seta para baixo */
  protected readonly downIconClasses = computed(() =>
    this.isActive() && this.currentDir() === 'desc'
      ? 'text-primary'
      : 'text-neutral-300 dark:text-neutral-600',
  );

  /** Alterna a direção da ordenação: asc → desc → asc */
  toggleSort(): void {
    const nextDir: SortDirection = this.isActive() && this.currentDir() === 'asc' ? 'desc' : 'asc';
    this.sortChange.emit({ field: this.field(), dir: nextDir });
  }
}
