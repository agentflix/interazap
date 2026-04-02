import { DOCUMENT } from '@angular/common';
import { DestroyRef, Injectable, computed, effect, inject, signal, untracked } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { type AfTabItem } from 'src/app/shared/components/tabs/tabs';
import {
  type Called,
  type CalledCounts,
  type CalledSentiment,
  type CalledSortBy,
  CalledService,
} from 'src/app/core/services/called.service';
import { ChatRealtimeService } from 'src/app/core/services/chat-realtime.service';
import { type CalledMessage } from 'src/app/core/services/called-message.service';
import { Subject, catchError, finalize, map, of, startWith, switchMap } from 'rxjs';
import { sentimentColor, sentimentLabel } from '../../../utils/sentiment';

/**
 * Estado e regras de negocio da listagem de tickets do chat.
 *
 * @remarks
 * Gerencia o estado da lista de tickets com sinais, processa eventos realtime
 * para atualizacoes incrementais, e suporta filtragem, ordenacao e paginacao.
 *
 * @example
 * ```typescript
 * const stateService = new ChatListStateService();
 * stateService.initialize();
 * stateService.setFilter('pending');
 * ```
 */
@Injectable()
export class ChatListStateService {
  private readonly calledService = inject(CalledService);
  private readonly realtimeService = inject(ChatRealtimeService);
  private readonly destroyRef = inject(DestroyRef);
  private readonly document = inject(DOCUMENT);

  private readonly listRefresh$ = new Subject<{ loadMore: boolean }>();
  private readonly filterStorageKey = 'agentflix:chat:filter';

  private readonly searchTerm = signal('');
  private readonly activeFilter = signal<'pending' | 'open' | 'all'>(this.restoreFilter());

  readonly sentimentFilter = signal<CalledSentiment | null>(null);
  readonly sortBy = signal<CalledSortBy>('last_message_at');
  readonly isFilterMenuOpen = signal(false);
  readonly calleds = signal<Called[]>([]);
  readonly isLoading = signal(false);
  readonly currentPage = signal(1);
  readonly hasMorePages = signal(true);
  readonly activeCalledId = signal<string | null>(null);
  readonly loadingPlaceholders = Array.from({ length: 5 }).map((_, index) => index);

  readonly counts = signal<CalledCounts>({
    all: 0,
    pending: 0,
    open: 0,
    closed: 0,
    in_progress: 0,
  });

  readonly hasActiveAdvancedFilter = computed(
    () => this.sentimentFilter() !== null || this.sortBy() !== 'last_message_at',
  );

  readonly filteredCalleds = computed(() => {
    let items = this.calleds();
    const filter = this.activeFilter();
    const sentiment = this.sentimentFilter();

    if (filter === 'pending') {
      items = items.filter((called) => called.status === 'pending');
    }

    if (filter === 'open') {
      items = items.filter((called) => called.status === 'open' || called.status === 'in_progress');
    }

    if (sentiment) {
      items = items.filter((called) => called.sentiment === sentiment);
    }

    return items;
  });

  readonly hasCalleds = computed(() => this.filteredCalleds().length > 0);
  readonly activeFilterLabel = computed(() => this.activeFilter());

  readonly tabsList = computed<AfTabItem[]>(() => {
    const currentCounts = this.counts();
    return [
      { id: 'pending', label: 'Fila', badge: currentCounts.pending },
      { id: 'open', label: 'Abertos', badge: currentCounts.open },
      { id: 'all', label: 'Todos', badge: currentCounts.all },
    ];
  });

  private reloadTimer: number | null = null;

  constructor() {
    effect(() => {
      const { version } = this.realtimeService.newTicket();
      if (version > 0) {
        untracked(() => this.reload());
      }
    });

    effect(() => {
      const { event, version } = this.realtimeService.ticketUpdated();
      if (event && version > 0) {
        untracked(() => this.updateTicketInList(event.ticket));
      }
    });

    effect(() => {
      const { event, version } = this.realtimeService.ticketSentimentUpdated();
      if (event && version > 0) {
        untracked(() =>
          this.updateTicketInList({
            ticket_id: event.ticket_id,
            sentiment: event.sentiment,
            sentiment_score: event.sentiment_score,
          }),
        );
      }
    });

    effect(() => {
      const { event, version } = this.realtimeService.newMessage();
      if (event && version > 0) {
        untracked(() => this.applyNewMessageToTicket(event.ticket_id, event.message));
      }
    });

    effect(() => {
      const { event, version } = this.realtimeService.messageStatus();
      if (event && version > 0) {
        untracked(() => this.applyMessageStatusToTicket(event));
      }
    });

    effect(() => {
      const { event, version } = this.realtimeService.edit();
      if (event && version > 0) {
        untracked(() => this.applyMessageEditToTicket(event));
      }
    });

    effect(() => {
      const { event, version } = this.realtimeService.reaction();
      if (event && version > 0) {
        untracked(() => this.applyMessageReactionToTicket(event));
      }
    });

    effect(() => {
      const { event, version } = this.realtimeService.delete();
      if (event && version > 0) {
        untracked(() => this.applyMessageDeleteToTicket(event));
      }
    });
  }

  /**
   * Inicializa pipeline de carregamento e dispara carga inicial.
   */
  initialize(): void {
    this.setupListLoadingPipeline();
  }

  /**
   * Atualiza o termo de busca e recarrega quando necessário.
   */
  setSearchTerm(value: string): void {
    this.searchTerm.set(value.trim());
    this.loadCalleds();
  }

  /**
   * Define o ticket ativo na lista.
   */
  setActiveCalledId(calledId: string | null): void {
    this.activeCalledId.set(calledId);
  }

  /**
   * Define filtro por status.
   */
  setFilter(filter: 'pending' | 'open' | 'all' | string): void {
    if (this.activeFilter() === filter) return;
    this.activeFilter.set(filter as 'pending' | 'open' | 'all');
    this.persistFilter(filter as 'pending' | 'open' | 'all');
  }

  /**
   * Define filtro por sentimento e recarrega.
   */
  setSentimentFilter(filter: CalledSentiment | null): void {
    if (this.sentimentFilter() === filter) {
      return;
    }
    this.sentimentFilter.set(filter);
    this.loadCalleds();
  }

  /**
   * Alterna ordenação entre recência e risco.
   */
  toggleSortBy(): void {
    this.sortBy.set(this.sortBy() === 'last_message_at' ? 'sentiment_score' : 'last_message_at');
    this.loadCalleds();
  }

  /**
   * Dispara carregamento da lista, com suporte à paginação incremental.
   */
  loadCalleds(loadMore = false): void {
    if (!loadMore) {
      this.currentPage.set(1);
    }
    this.listRefresh$.next({ loadMore });
  }

  /**
   * Recarregamento debounce para eventos realtime.
   */
  reload(): void {
    if (this.reloadTimer !== null) {
      clearTimeout(this.reloadTimer);
    }

    this.reloadTimer = window.setTimeout(() => {
      this.reloadTimer = null;
      this.loadCalleds();
    }, 300);
  }

  /**
   * Atualiza contagem de não lidos de um ticket para zero.
   */
  markTicketAsRead(ticketId: string): void {
    const current = this.calleds();
    const updated = current.map((called) =>
      called.id === ticketId ? { ...called, unread_count: 0 } : called,
    );
    this.calleds.set(updated);
  }

  /**
   * Indica se um ticket está ativo.
   */
  isActive(called: Called): boolean {
    return String(called.id) === String(this.activeCalledId());
  }

  /**
   * Resolve classes do badge de sentimento.
   */
  getSentimentBadgeClass(called: Called): string {
    const color = sentimentColor(called.sentiment_score ?? null);
    if (color === 'red') return 'bg-red-100 text-red-800';
    if (color === 'orange') return 'bg-orange-100 text-orange-800';
    if (color === 'yellow') return 'bg-yellow-100 text-yellow-800';
    if (color === 'green') return 'bg-green-100 text-green-800';
    return 'bg-neutral-100 text-neutral-600';
  }

  /**
   * Resolve label de sentimento.
   */
  getSentimentLabel(called: Called): string {
    return sentimentLabel(called.sentiment_score ?? null);
  }

  /**
   * Nome de exibição para o ticket.
   */
  getDisplayName(called: Called): string {
    const contactName = called.contact?.name;
    if (contactName !== null && contactName !== undefined && contactName !== '') {
      return contactName;
    }

    const protocol = called.protocol;
    if (protocol !== null && protocol !== undefined && protocol !== '') {
      return protocol;
    }

    return 'Atendimento';
  }

  /**
   * Subtítulo de listagem.
   */
  getSubtitle(called: Called): string {
    const lastContent = called.last_message?.content;
    if (lastContent !== null && lastContent !== undefined && lastContent !== '') {
      return lastContent;
    }

    const subject = called.subject;
    if (subject !== null && subject !== undefined && subject !== '') {
      return subject;
    }

    return 'Sem mensagens';
  }

  /**
   * Formata horário relativo.
   */
  formatTime(value?: string | null): string {
    if (value === null || value === undefined || value === '') return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return '';

    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffSec = Math.floor(diffMs / 1000);
    const diffMin = Math.floor(diffSec / 60);
    const diffHour = Math.floor(diffMin / 60);
    const diffDays = Math.floor(diffHour / 24);

    if (diffSec < 60) return 'agora';
    if (diffMin < 60) return `${diffMin} min`;
    if (diffHour < 24) return `${diffHour} h`;
    if (diffDays === 1) return 'ontem';
    if (diffDays < 7) return `${diffDays} dias`;

    return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
  }

  /**
   * Timestamp de referência do ticket.
   */
  getLastTimestamp(called: Called): string {
    return this.firstNonEmpty([
      called.last_message?.created_at,
      called.updated_at,
      called.created_at,
    ]);
  }

  /**
   * Aplica atualização parcial em um ticket já existente na lista.
   */
  updateTicketInList(ticketData: Record<string, unknown>): void {
    this.applyTicketPatch(ticketData);
  }

  /**
   * Libera recursos explícitos do serviço.
   */
  destroy(): void {
    if (this.reloadTimer !== null) {
      clearTimeout(this.reloadTimer);
      this.reloadTimer = null;
    }
    this.listRefresh$.complete();
  }

  private setupListLoadingPipeline(): void {
    this.listRefresh$
      .pipe(
        startWith({ loadMore: false }),
        switchMap(({ loadMore }) => {
          this.isLoading.set(true);
          return this.calledService.list(this.buildListParams()).pipe(
            map((response) => ({
              items: response.data ?? [],
              meta: response.meta,
              counts: response.counts,
              loadMore,
            })),
            catchError(() =>
              of({
                items: [],
                loadMore,
                counts: undefined,
                meta: { current_page: 1, last_page: 1 },
              }),
            ),
            finalize(() => this.isLoading.set(false)),
          );
        }),
        takeUntilDestroyed(this.destroyRef),
      )
      .subscribe(({ items, loadMore, meta, counts }) => {
        if (counts !== undefined) {
          this.counts.set(counts);
        } else {
          this.syncCountsFromCalleds(items);
        }

        this.hasMorePages.set(meta.current_page < meta.last_page);

        if (loadMore) {
          const currentItems = this.calleds();
          const existingIds = new Set(currentItems.map((item) => String(item.id)));
          const newItems = items.filter((item) => !existingIds.has(String(item.id)));
          this.updateWithTransition([...currentItems, ...newItems]);
          return;
        }

        this.updateWithTransition(items);
      });
  }

  private buildListParams(): {
    search?: string;
    sentiment?: CalledSentiment;
    sort_by: CalledSortBy;
    per_page: number;
    page: number;
  } {
    const search = this.searchTerm();
    const sentiment = this.sentimentFilter();

    return {
      search: search === '' ? undefined : search,
      sentiment: sentiment ?? undefined,
      sort_by: this.sortBy(),
      per_page: 30,
      page: this.currentPage(),
    };
  }

  private applyTicketPatch(ticketData: Record<string, unknown>): void {
    const current = this.calleds();
    const updated = current.map((called) => {
      const payloadId = ticketData['id'] ?? ticketData['ticket_id'];
      if (String(called.id) === String(payloadId)) {
        const latestMessage = ticketData['latest_message'] as Called['last_message'] | undefined;
        const sentiment = (ticketData['sentiment'] as Called['sentiment']) ?? called.sentiment;
        const sentimentScore =
          (ticketData['sentiment_score'] as Called['sentiment_score']) ?? called.sentiment_score;

        return {
          ...called,
          protocol: (ticketData['protocol'] as string | null | undefined) ?? called.protocol,
          last_message: latestMessage ?? called.last_message,
          sentiment,
          sentiment_score: sentimentScore,
          updated_at: (ticketData['updated_at'] as string) ?? called.updated_at,
        };
      }
      return called;
    });

    const sorted = this.sortCalleds(updated);
    this.calleds.set(sorted);
  }

  private applyNewMessageToTicket(ticketId: string, message: unknown): void {
    const incomingMessage = message as CalledMessage;
    const current = this.calleds();
    const updated = current.map((called) => {
      if (String(called.id) !== String(ticketId)) {
        return called;
      }

      const direction = (incomingMessage.direction ?? '').toLowerCase();
      const shouldIncrementUnread = direction === 'incoming';

      return {
        ...called,
        last_message: {
          id: incomingMessage.id,
          content: incomingMessage.content,
          type: incomingMessage.type,
          created_at: incomingMessage.created_at,
        },
        updated_at: incomingMessage.created_at ?? called.updated_at,
        unread_count: shouldIncrementUnread ? (called.unread_count ?? 0) + 1 : called.unread_count,
      };
    });

    this.updateWithTransition(updated);
  }

  private applyMessageStatusToTicket(event: {
    message_id?: string;
    external_id?: string;
    status: string;
  }): void {
    const updated = this.calleds().map((called) => {
      const lastMessage = called.last_message;
      if (!lastMessage) {
        return called;
      }

      const matchesId =
        event.message_id !== undefined && String(lastMessage.id) === String(event.message_id);

      if (!matchesId) {
        return called;
      }

      return {
        ...called,
        last_message: {
          ...lastMessage,
          type: lastMessage.type,
          content: lastMessage.content,
          created_at: lastMessage.created_at,
        },
      };
    });

    this.calleds.set(updated);
  }

  private applyMessageEditToTicket(event: {
    message_id: string;
    ticket_id: string;
    content: string;
  }): void {
    const updated = this.calleds().map((called) => {
      if (String(called.id) !== String(event.ticket_id) || !called.last_message) {
        return called;
      }

      if (String(called.last_message.id) !== String(event.message_id)) {
        return called;
      }

      return {
        ...called,
        last_message: {
          ...called.last_message,
          content: event.content,
        },
      };
    });

    this.calleds.set(updated);
  }

  private applyMessageReactionToTicket(event: { message_id: string; ticket_id: string }): void {
    const hasMatchingLastMessage = this.calleds().some(
      (called) =>
        String(called.id) === String(event.ticket_id) &&
        String(called.last_message?.id) === String(event.message_id),
    );

    if (!hasMatchingLastMessage) {
      return;
    }

    this.calleds.set([...this.calleds()]);
  }

  private applyMessageDeleteToTicket(event: { message_id: string; ticket_id: string }): void {
    const updated = this.calleds().map((called) => {
      if (String(called.id) !== String(event.ticket_id) || !called.last_message) {
        return called;
      }

      if (String(called.last_message.id) !== String(event.message_id)) {
        return called;
      }

      return {
        ...called,
        last_message: {
          ...called.last_message,
          content: 'Mensagem removida',
        },
      };
    });

    this.calleds.set(updated);
  }

  private updateWithTransition(newItems: Called[]): void {
    const doc = this.document as Document & {
      startViewTransition?: (callback: () => void) => void;
    };
    const sorted = this.sortCalleds(newItems);

    if (typeof doc.startViewTransition !== 'function') {
      this.calleds.set(sorted);
      return;
    }

    doc.startViewTransition(() => {
      this.calleds.set(sorted);
    });
  }

  private sortCalleds(items: Called[]): Called[] {
    if (this.sortBy() === 'sentiment_score') {
      return [...items].sort((a, b) => {
        const scoreA = a.sentiment_score ?? -1;
        const scoreB = b.sentiment_score ?? -1;
        if (scoreA !== scoreB) {
          return scoreB - scoreA;
        }

        const updatedA = this.toTime(a.updated_at);
        const updatedB = this.toTime(b.updated_at);
        return updatedB - updatedA;
      });
    }

    return [...items].sort((a, b) => {
      const dateA = this.toTime(a.last_message?.created_at) || this.toTime(a.updated_at);
      const dateB = this.toTime(b.last_message?.created_at) || this.toTime(b.updated_at);
      return dateB - dateA;
    });
  }

  private toTime(value: string | null | undefined): number {
    if (value === null || value === undefined || value === '') {
      return 0;
    }

    const timestamp = new Date(value).getTime();
    if (Number.isNaN(timestamp)) {
      return 0;
    }

    return timestamp;
  }

  private firstNonEmpty(values: (string | null | undefined)[]): string {
    for (const value of values) {
      if (value !== null && value !== undefined && value !== '') {
        return value;
      }
    }

    return '';
  }

  private syncCountsFromCalleds(items: Called[]): void {
    const next: CalledCounts = {
      all: items.length,
      pending: 0,
      open: 0,
      closed: 0,
      in_progress: 0,
    };

    for (const called of items) {
      if (called.status === 'pending') {
        next.pending += 1;
      } else if (called.status === 'open') {
        next.open += 1;
      } else if (called.status === 'closed') {
        next.closed += 1;
      } else if (called.status === 'in_progress') {
        next.in_progress = (next.in_progress ?? 0) + 1;
        next.open += 1;
      }
    }

    this.counts.set(next);
  }

  private restoreFilter(): 'pending' | 'open' | 'all' {
    if (typeof window === 'undefined') return 'all';
    const stored = window.localStorage.getItem(this.filterStorageKey);
    if (stored === 'pending' || stored === 'open' || stored === 'all') {
      return stored;
    }
    return 'all';
  }

  private persistFilter(filter: 'pending' | 'open' | 'all'): void {
    if (typeof window === 'undefined') return;
    window.localStorage.setItem(this.filterStorageKey, filter);
  }
}
