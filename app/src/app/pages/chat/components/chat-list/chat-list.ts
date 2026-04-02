import {
  type AfterViewInit,
  ChangeDetectionStrategy,
  Component,
  DestroyRef,
  ElementRef,
  Input,
  NgZone,
  ViewChild,
  inject,
  effect,
  untracked,
  type OnDestroy,
  type OnInit,
} from '@angular/core';
import { ActivatedRoute, NavigationEnd, Router } from '@angular/router';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { fromEvent } from 'rxjs';
import { type Called, type CalledSentiment } from 'src/app/core/services/called.service';
import { ChatRealtimeService } from 'src/app/core/services/chat-realtime.service';
import { AuthStoreService } from 'src/app/core/services/auth-store.service';
import { AfScrollAreaComponent } from 'src/app/shared/components/scroll-area/scroll-area';
import { ChatListFiltersComponent } from './components/chat-list-filters/chat-list-filters.component';
import { ChatListItemComponent } from './components/chat-list-item/chat-list-item.component';
import { ChatListStateService } from './services/chat-list-state.service';

/**
 * Container da listagem de tickets do chat.
 *
 * @remarks
 * Responsável apenas por integração de UI (rota e scroll), delegando
 * estado e regras de negócio para o serviço `ChatListStateService`.
 *
 * @example
 * ```html
 * <app-chat-list [search]="searchTerm" />
 * ```
 */
@Component({
  selector: 'app-chat-list',
  standalone: true,
  imports: [AfScrollAreaComponent, ChatListFiltersComponent, ChatListItemComponent],
  templateUrl: './chat-list.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  host: {
    class: 'block h-full',
  },
  providers: [ChatListStateService],
})
export class ChatList implements OnInit, OnDestroy, AfterViewInit {
  readonly state = inject(ChatListStateService);

  private readonly destroyRef = inject(DestroyRef);
  private readonly router = inject(Router);
  private readonly route = inject(ActivatedRoute);
  private readonly ngZone = inject(NgZone);
  private readonly realtime = inject(ChatRealtimeService);
  private readonly authStore = inject(AuthStoreService);

  private currentRealtimeTicketId: string | null = null;

  @ViewChild('scrollContainer', { read: ElementRef })
  private readonly scrollContainer?: ElementRef<HTMLElement>;

  private scrollElement: HTMLElement | null = null;
  private lastScrollTop = 0;
  private initialized = false;
  private pendingSearch = '';

  readonly calleds = this.state.calleds;
  readonly isLoading = this.state.isLoading;
  readonly counts = this.state.counts;
  readonly activeCalledId = this.state.activeCalledId;
  readonly filteredCalleds = this.state.filteredCalleds;
  readonly hasCalleds = this.state.hasCalleds;
  readonly activeFilterLabel = this.state.activeFilterLabel;
  readonly tabsList = this.state.tabsList;
  readonly loadingPlaceholders = this.state.loadingPlaceholders;
  readonly hasMorePages = this.state.hasMorePages;
  readonly currentPage = this.state.currentPage;
  readonly sentimentFilter = this.state.sentimentFilter;
  readonly sortBy = this.state.sortBy;
  readonly hasActiveAdvancedFilter = this.state.hasActiveAdvancedFilter;
  readonly isFilterMenuOpen = this.state.isFilterMenuOpen;

  constructor() {
    effect(() => {
      const activeId = this.activeCalledId();

      untracked(() => {
        this.syncRealtimeTicketRoom(activeId);
      });
    });
  }

  /**
   * Atualiza termo de busca vindo do pai.
   */
  @Input() set search(value: string | null | undefined) {
    this.pendingSearch = (value ?? '').trim();
    if (!this.initialized) {
      return;
    }
    this.state.setSearchTerm(this.pendingSearch);
  }

  ngOnInit(): void {
    const tenantId = this.authStore.user()?.tenant_id;
    if (tenantId) {
      this.realtime.connect(String(tenantId));
    }

    this.initialized = true;
    this.state.initialize();
    if (this.pendingSearch !== '') {
      this.state.setSearchTerm(this.pendingSearch);
    }
    this.updateActiveFromRoute();

    this.router.events.pipe(takeUntilDestroyed(this.destroyRef)).subscribe((event) => {
      if (event instanceof NavigationEnd) {
        this.updateActiveFromRoute();
      }
    });
  }

  ngAfterViewInit(): void {
    queueMicrotask(() => {
      this.scrollElement = this.findScrollElement();
      if (!this.scrollElement) return;

      this.ngZone.runOutsideAngular(() => {
        if (!this.scrollElement) return;
        fromEvent(this.scrollElement, 'scroll')
          .pipe(takeUntilDestroyed(this.destroyRef))
          .subscribe(() => this.handleScroll());
      });
    });
  }

  ngOnDestroy(): void {
    this.syncRealtimeTicketRoom(null);
    this.state.destroy();
  }

  /**
   * Define filtro de status.
   */
  setFilter(filter: 'pending' | 'open' | 'all' | string): void {
    this.state.setFilter(filter);
  }

  /**
   * Define filtro de sentimento.
   */
  setSentimentFilter(filter: CalledSentiment | null): void {
    this.state.setSentimentFilter(filter);
  }

  /**
   * Recarrega listagem.
   */
  loadCalleds(loadMore = false): void {
    this.state.loadCalleds(loadMore);
  }

  /**
   * Recarrega dados da lista com debounce.
   */
  reload(): void {
    this.state.reload();
  }

  /**
   * Marca ticket como lido.
   */
  markTicketAsRead(ticketId: string): void {
    this.state.markTicketAsRead(ticketId);
  }

  /**
   * Formata data relativa.
   */
  formatTime(value?: string | null): string {
    return this.state.formatTime(value);
  }

  /**
   * Obtém timestamp de referência do ticket.
   */
  getLastTimestamp(called: Called): string {
    return this.state.getLastTimestamp(called);
  }

  /**
   * Indica se ticket está ativo.
   */
  isActive(called: Called): boolean {
    return this.state.isActive(called);
  }

  /**
   * Utilitário para testes e integração com eventos locais.
   */
  updateTicketInList(ticketData: Record<string, unknown>): void {
    this.state.updateTicketInList(ticketData);
  }

  private findScrollElement(): HTMLElement | null {
    const host = this.scrollContainer?.nativeElement;
    if (!host) return null;
    return (host.querySelector('.af-scroll-area--scrollable') as HTMLElement) ?? host;
  }

  private handleScroll(): void {
    if (!this.scrollElement || this.state.isLoading() || !this.state.hasMorePages()) {
      return;
    }

    const currentScrollTop = this.scrollElement.scrollTop;
    const isScrollingUp = currentScrollTop < this.lastScrollTop;

    if (isScrollingUp && currentScrollTop < 200) {
      this.ngZone.run(() => {
        const nextPage = this.state.currentPage() + 1;
        this.state.currentPage.set(nextPage);
        this.state.loadCalleds(true);
      });
    }

    this.lastScrollTop = currentScrollTop;
  }

  private updateActiveFromRoute(): void {
    let current = this.route.snapshot;
    while (current.firstChild) {
      current = current.firstChild;
    }
    this.state.setActiveCalledId(current.paramMap.get('calledId'));
  }

  private syncRealtimeTicketRoom(nextTicketId: string | null): void {
    if (this.currentRealtimeTicketId === nextTicketId) {
      return;
    }

    if (this.currentRealtimeTicketId) {
      this.realtime.leaveTicket(this.currentRealtimeTicketId);
    }

    this.currentRealtimeTicketId = nextTicketId;

    if (nextTicketId) {
      this.realtime.joinTicket(nextTicketId);
    }
  }
}
