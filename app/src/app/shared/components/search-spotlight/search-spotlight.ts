import {
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  HostListener,
  computed,
  effect,
  inject,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { FormControl, ReactiveFormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { LucideAngularModule } from 'lucide-angular';
import {
  catchError,
  debounceTime,
  distinctUntilChanged,
  finalize,
  of,
  Subject,
  switchMap,
} from 'rxjs';
import { GlobalSearchService } from '@core/services/global-search.service';
import { SearchSpotlightService } from '@core/services/search-spotlight.service';
import {
  type GlobalSearchItem,
  type GlobalSearchResponse,
  type RecentSearch,
} from '@shared/models/global-search.model';
import { AfButtonComponent } from '../button/button';
import { AfEmptyStateComponent } from '../empty-state/empty-state';
import { AfSearchInputComponent } from '../search-input/search-input';

type SearchGroupKey = 'contacts' | 'companies' | 'negotiations' | 'tickets' | 'users';

interface SpotlightGroup {
  key: SearchGroupKey;
  label: string;
  route: string;
  total: number;
  items: GlobalSearchItem[];
}

/**
 * Spotlight de busca global com resultados agrupados por tipo de entidade.
 * Permite buscar contatos, empresas, negociações, tickets e usuários
 * com navegação por teclado e histórico de buscas recentes.
 */
@Component({
  selector: 'af-search-spotlight, app-search-spotlight',
  standalone: true,
  imports: [
    ReactiveFormsModule,
    LucideAngularModule,
    AfButtonComponent,
    AfSearchInputComponent,
    AfEmptyStateComponent,
  ],
  templateUrl: './search-spotlight.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class SearchSpotlightComponent {
  private readonly searchService = inject(GlobalSearchService);
  private readonly spotlightService = inject(SearchSpotlightService);
  private readonly router = inject(Router);
  private readonly destroyRef = inject(DestroyRef);

  readonly isOpen = signal(false);
  readonly query = signal('');
  readonly results = signal<GlobalSearchResponse | null>(null);
  readonly isLoading = signal(false);
  readonly hasError = signal(false);
  readonly selectedIndex = signal(-1);

  private readonly recentHistory = signal<RecentSearch[]>([]);
  private readonly searchTrigger = new Subject<string>();

  readonly queryControl = new FormControl('', { nonNullable: true });

  readonly groups = computed<SpotlightGroup[]>(() => {
    const response = this.results();
    const data = response?.data;

    if (!data) {
      return [];
    }

    return [
      this.mapGroup('contacts', 'Contatos CRM', '/crm/contacts', data.contacts),
      this.mapGroup('companies', 'Empresas CRM', '/crm/companies', data.companies),
      this.mapGroup('negotiations', 'Negociações CRM', '/crm/negotiations', data.negotiations),
      this.mapGroup('tickets', 'Tickets', '/chat', data.tickets),
      this.mapGroup('users', 'Usuários', '/platform/users', data.users),
    ].filter((group): group is SpotlightGroup => group !== null);
  });

  readonly flatResults = computed<GlobalSearchItem[]>(() =>
    this.groups().flatMap((group) => group.items),
  );

  readonly recentSearches = computed<RecentSearch[]>(() => this.recentHistory());

  readonly hasResults = computed<boolean>(() => this.flatResults().length > 0);

  readonly showInitialState = computed<boolean>(() => this.query().trim().length === 0);

  constructor() {
    this.queryControl.valueChanges
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe((value) => this.onQueryChange(value));

    this.searchTrigger
      .pipe(
        debounceTime(300),
        distinctUntilChanged(),
        switchMap((value) => {
          const normalized = value.trim();
          if (normalized.length < 2) {
            this.isLoading.set(false);
            this.hasError.set(false);
            this.results.set(null);
            return of<GlobalSearchResponse | null>(null);
          }

          this.isLoading.set(true);
          this.hasError.set(false);

          return this.searchService.search(normalized).pipe(
            catchError(() => {
              this.hasError.set(true);
              return of<GlobalSearchResponse | null>(null);
            }),
            finalize(() => this.isLoading.set(false)),
          );
        }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe((response) => {
        this.results.set(response);
        this.selectedIndex.set(-1);
      });

    effect(() => {
      if (this.spotlightService.requestOpen()) {
        this.open();
        this.spotlightService.clear();
      }
    });
  }

  /** Abre o spotlight e carrega buscas recentes */
  open(): void {
    this.isOpen.set(true);
    this.recentHistory.set(this.searchService.getRecentSearches());

    queueMicrotask(() => {
      const input = document.querySelector<HTMLInputElement>('[data-test="spotlight-query"]');
      input?.focus();
    });
  }

  /** Fecha o spotlight e limpa o estado */
  close(): void {
    this.isOpen.set(false);
    this.queryControl.setValue('', { emitEvent: false });
    this.query.set('');
    this.results.set(null);
    this.selectedIndex.set(-1);
    this.hasError.set(false);
    this.isLoading.set(false);
    this.recentHistory.set([]);
  }

  /** Processa mudança no termo de busca */
  onQueryChange(value: string): void {
    this.query.set(value);
    this.selectedIndex.set(-1);
    this.searchTrigger.next(value);
  }

  /** Trata eventos de teclado para navegação */
  onKeydown(event: KeyboardEvent): void {
    if (!this.isOpen()) {
      return;
    }

    const items = this.flatResults();

    if (event.key === 'ArrowDown' && items.length > 0) {
      event.preventDefault();
      const nextIndex = (this.selectedIndex() + 1) % items.length;
      this.selectedIndex.set(nextIndex);
      return;
    }

    if (event.key === 'ArrowUp' && items.length > 0) {
      event.preventDefault();
      const previousIndex = this.selectedIndex() <= 0 ? items.length - 1 : this.selectedIndex() - 1;
      this.selectedIndex.set(previousIndex);
      return;
    }

    if (event.key === 'Enter' && this.selectedIndex() >= 0) {
      event.preventDefault();
      const selectedItem = items[this.selectedIndex()];
      if (selectedItem) {
        this.navigateTo(selectedItem);
      }
      return;
    }

    if (event.key === 'Escape') {
      event.preventDefault();
      this.close();
    }
  }

  /** Navega para o item selecionado */
  navigateTo(item: GlobalSearchItem): void {
    this.searchService.addRecentSearch(item);
    this.performNavigation(item.url);
  }

  /** Navega para busca recente */
  navigateRecent(item: RecentSearch): void {
    this.searchService.addRecentSearch({
      id: item.id,
      type: item.type,
      label: item.label,
      url: item.url,
      icon: 'tablerHistory',
    });
    this.performNavigation(item.url);
  }

  /** Faz parse da URL com query string e navega via Router. */
  private performNavigation(url: string): void {
    const [path, qs] = url.split('?');
    if (qs) {
      const queryParams: Record<string, string> = {};
      for (const pair of qs.split('&')) {
        const [key, value] = pair.split('=');
        if (key) {
          queryParams[key] = decodeURIComponent(value ?? '');
        }
      }
      void this.router.navigate([path], { queryParams });
    } else {
      void this.router.navigateByUrl(url);
    }
    this.close();
  }

  /** Tenta novamente a busca após erro */
  retry(): void {
    this.searchTrigger.next(this.query());
  }

  /** Abre o grupo completo de resultados */
  openGroup(group: SpotlightGroup): void {
    const query = this.query().trim();
    if (!query) {
      void this.router.navigateByUrl(group.route);
      this.close();
      return;
    }

    void this.router.navigate([group.route], { queryParams: { search: query } });
    this.close();
  }

  /** Índice plano do item nos resultados */
  flatIndexByItem(item: GlobalSearchItem): number {
    return this.flatResults().findIndex(
      (candidate) => candidate.id === item.id && candidate.type === item.type,
    );
  }

  /** Destaca o termo de busca no rótulo */
  highlightLabel(label: string): string {
    const term = this.query().trim();
    const escapedLabel = this.escapeHtml(label);

    if (term.length < 2) {
      return escapedLabel;
    }

    const escapedTerm = term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    const matcher = new RegExp(`(${escapedTerm})`, 'ig');

    return escapedLabel.replace(
      matcher,
      '<mark class="bg-warning/15 dark:bg-warning/25 text-neutral-900 dark:text-neutral-50 rounded-sm px-0.5">$1</mark>',
    );
  }

  /** Limpa buscas recentes */
  clearRecentSearches(): void {
    this.searchService.clearRecentSearches();
    this.recentHistory.set([]);
  }

  @HostListener('document:keydown.meta.k', ['$event'])
  @HostListener('document:keydown.control.k', ['$event'])
  onShortcut(event: Event): void {
    const keyboardEvent = event as KeyboardEvent;
    keyboardEvent.preventDefault();
    this.open();
  }

  @HostListener('document:keydown.escape', ['$event'])
  onGlobalEscape(event: Event): void {
    if (!this.isOpen()) {
      return;
    }

    const keyboardEvent = event as KeyboardEvent;
    keyboardEvent.preventDefault();
    this.close();
  }

  private mapGroup(
    key: SearchGroupKey,
    label: string,
    route: string,
    source?: { total: number; items: GlobalSearchItem[] },
  ): SpotlightGroup | null {
    if (!source) {
      return null;
    }

    return {
      key,
      label,
      route,
      total: source.total,
      items: source.items,
    };
  }

  private escapeHtml(value: string): string {
    return value
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }
}
