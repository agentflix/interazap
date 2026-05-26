import {
  type TemplateRef,
  Component,
  ChangeDetectionStrategy,
  input,
  output,
  signal,
  computed,
  contentChild,
} from '@angular/core';
import { NgTemplateOutlet } from '@angular/common';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfButtonComponent } from '../button/button';
import { AfIconButtonComponent } from '../icon-button/icon-button';
import { AfSpinnerComponent } from '../spinner/spinner';
import { AfEmptyStateComponent } from '../empty-state/empty-state';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Container de lista completo com busca, filtros, ações de criar/excluir,
 * overlay de carregamento, estado vazio e paginação.
 *
 * @example
 * ```html
 * <af-unified-list
 *   [isLoading]="loading()"
 *   [total]="meta().total"
 *   [currentPage]="meta().currentPage"
 *   [lastPage]="meta().lastPage"
 *   (searchChange)="onSearch($event)"
 *   (pageChange)="loadPage($event)"
 *   (createClick)="openCreate()"
 * >
 *   <ng-template #tableHeader>...</ng-template>
 *   <ng-template #tableBody>...</ng-template>
 * </af-unified-list>
 * ```
 */
@Component({
  selector: 'af-unified-list',
  standalone: true,
  imports: [
    NgTemplateOutlet,
    ReactiveFormsModule,
    AfButtonComponent,
    AfIconButtonComponent,
    AfSpinnerComponent,
    AfEmptyStateComponent,
    LucideAngularModule,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './unified-list.html',
})
export class AfUnifiedListComponent {
  /** Estado de carregamento */
  readonly isLoading = input(false);

  /** Total de itens */
  readonly total = input(0);

  /** Itens por página */
  readonly perPage = input(10);

  /** Número da página atual */
  readonly currentPage = input(1);

  /** Número da última página */
  readonly lastPage = input(1);

  /** Indica se o modo de seleção está ativo */
  readonly hasSelection = input(false);

  /** Número de itens selecionados */
  readonly selectedCount = input(0);

  /** Placeholder da busca */
  readonly searchPlaceholder = input('Buscar por...');

  /** Rótulo do botão de criar */
  readonly createLabel = input('Novo');

  /** Título do estado vazio */
  readonly emptyTitle = input('Nenhum item encontrado');

  /** Descrição do estado vazio */
  readonly emptyDescription = input('Tente ajustar os filtros ou criar um novo item.');

  /** Emitido ao alterar o texto de busca */
  readonly searchChange = output<string>();

  /** Emitido ao alterar a página */
  readonly pageChange = output<number>();

  /** Emitido ao clicar no botão de filtro */
  readonly filterClick = output<void>();

  /** Emitido ao clicar no botão de criar */
  readonly createClick = output<void>();

  /** Emitido ao clicar em excluir selecionados */
  readonly deleteSelectedClick = output<void>();

  /** Template de cabeçalho da tabela projetado via content */
  readonly headerTpl = contentChild.required<TemplateRef<unknown>>('tableHeader');

  /** Template de corpo da tabela projetado via content */
  readonly bodyTpl = contentChild.required<TemplateRef<unknown>>('tableBody');

  protected readonly searchControl = new FormControl('', { nonNullable: true });

  protected readonly isEmpty = computed(() => this.total() === 0);
  protected readonly showingFrom = computed(() =>
    this.total() === 0 ? 0 : (this.currentPage() - 1) * this.perPage() + 1,
  );
  protected readonly showingTo = computed(() =>
    Math.min(this.currentPage() * this.perPage(), this.total()),
  );
  protected readonly pages = computed(() => {
    const pages: number[] = [];
    for (let i = 1; i <= this.lastPage(); i++) {
      pages.push(i);
    }
    return pages.length > 7 ? this.paginateRange(this.currentPage(), this.lastPage()) : pages;
  });

  constructor() {
    this.searchControl.valueChanges.subscribe((v) => this.searchChange.emit(v));
  }

  private paginateRange(current: number, last: number): number[] {
    const delta = 2;
    const range: number[] = [];
    for (let i = Math.max(2, current - delta); i <= Math.min(last - 1, current + delta); i++) {
      range.push(i);
    }
    if (current - delta > 2) range.unshift(-1);
    if (current + delta < last - 1) range.push(-1);
    range.unshift(1);
    if (last > 1) range.push(last);
    return range;
  }
}
