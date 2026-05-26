import {
  Component,
  ChangeDetectionStrategy,
  input,
  output,
  DestroyRef,
  inject,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { AfPageTitleComponent } from '../page-title/page-title';
import { AfSearchInputComponent } from '../search-input/search-input';
import { AfEmptyStateComponent } from '../empty-state/empty-state';
import { AfPaginationComponent } from '../pagination/pagination';
import { AfSkeletonTableRowComponent } from '../skeleton-table-row/skeleton-table-row';
import { AfButtonComponent } from '../button/button';
import { LucideAngularModule } from 'lucide-angular';

/**
 * Layout padrão de listagem CRUD que orquestra título, barra de busca,
 * slot de conteúdo, paginação e estado vazio.
 *
 * @example
 * ```html
 * <af-crud-page
 *   title="Contatos"
 *   subtitle="Gerencie seus contatos"
 *   createLabel="Novo Contato"
 *   [loading]="isLoading()"
 *   [empty]="contacts().length === 0"
 *   [currentPage]="meta.current_page"
 *   [lastPage]="meta.last_page"
 *   [perPage]="meta.per_page"
 *   [total]="meta.total"
 *   (create)="openCreateModal()"
 *   (search)="onSearch($event)"
 *   (pageChange)="loadPage($event)"
 * >
 *   <table class="w-full">...</table>
 * </af-crud-page>
 * ```
 */
@Component({
  selector: 'af-crud-page, app-crud-page',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    AfPageTitleComponent,
    AfSearchInputComponent,
    AfEmptyStateComponent,
    AfPaginationComponent,
    AfSkeletonTableRowComponent,
    AfButtonComponent,
    LucideAngularModule,
  ],
  changeDetection: ChangeDetectionStrategy.OnPush,
  templateUrl: './crud-page.html',
})
export class AfCrudPageComponent<TItem = unknown> {
  /** Título da página */
  readonly title = input.required<string>();

  /** Subtítulo opcional */
  readonly subtitle = input<string | null>(null);

  /** Rótulo do botão de criação (omitir para ocultar) */
  readonly createLabel = input<string>();

  /** Placeholder do campo de busca */
  readonly searchPlaceholder = input('Buscar...');

  /** Rótulo opcional do campo de busca */
  readonly searchLabel = input<string>();

  /** Indica se os dados estão sendo carregados */
  readonly loading = input(false);

  /** Indica se o conjunto de dados está vazio (após carregamento) */
  readonly empty = input(false);

  /** Título do estado vazio */
  readonly emptyTitle = input('Nenhum registro encontrado');

  /** Descrição do estado vazio */
  readonly emptyDescription = input('Crie seu primeiro registro para começar.');

  /** Número de colunas do skeleton */
  readonly skeletonColumns = input(4);

  /** Número de linhas do skeleton */
  readonly skeletonRows = input(5);

  /** Página atual (índice a partir de 1) */
  readonly currentPage = input(1);

  /** Última página disponível */
  readonly lastPage = input(1);

  /** Itens por página */
  readonly perPage = input(15);

  /** Total de registros */
  readonly total = input(0);

  /** Serviço CRUD legado */
  readonly service = input<unknown>();

  /** Configuração CRUD legada */
  readonly config = input<unknown>();

  /** Colunas legadas */
  readonly columns = input<unknown>();

  /** Ações legadas */
  readonly actions = input<unknown>();

  /** Emitido quando o botão de criação é clicado */
  readonly create = output<void>();

  /** Emitido quando o termo de busca muda */
  // eslint-disable-next-line @angular-eslint/no-output-native
  readonly search = output<string>();

  /** Emitido quando o usuário navega para uma página diferente */
  readonly pageChange = output<number>();

  /** Saída de ação customizada legada — emite ação e item alvo */
  readonly customAction = output<{ action: string; item: TItem }>();

  /** Controle interno de busca */
  protected readonly searchControl = new FormControl('', { nonNullable: true });

  /** Controle de status temporário legado */
  readonly tempStatusControl = new FormControl<string>('');

  private readonly destroyRef = inject(DestroyRef);

  constructor() {
    this.searchControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.search.emit(value));
  }

  /** Emite o evento de criação */
  openCreate(): void {
    this.create.emit();
  }

  /** Placeholder para tratamento de item salvo (herança) */
  handleFormSaved(_item: TItem): void {}

  /** Placeholder para fechamento de modal (herança) */
  closeModal(): void {}

  /** Placeholder para recarregamento da lista (herança) */
  reloadList(): void {}

  /** Emite a página selecionada para o pai */
  goToPage(page: number): void {
    this.pageChange.emit(page);
  }

  /** Placeholder para exibição de erro (herança) */
  showError(_message: string): void {}

  /** Placeholder para exibição de sucesso (herança) */
  showSuccess(_message: string): void {}
}

export { AfCrudPageComponent as CrudPageComponent };
