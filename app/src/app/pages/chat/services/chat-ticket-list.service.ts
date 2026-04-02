import { DestroyRef, effect, inject, Injectable, signal, type OnDestroy } from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { ActivatedRoute } from '@angular/router';
import {
  CalledService,
  type Called,
  type CalledCounts,
  type CalledStatus,
} from 'src/app/core/services/called.service';
import { ChatRefreshService } from 'src/app/core/services/chat-refresh.service';
import { ChatStore as ChatRealtimeStore } from '../store/chat.store';

/**
 * Service for managing the list of chat tickets.
 *
 * Manages the global ticket list, counts, loading states, search,
 * and transient status overrides. Does NOT manage ticket selection
 * (use ChatStore for that).
 *
 * @example
 * ```typescript
 * const ticketList = inject(ChatTicketListService);
 * ticketList.loadTickets();
 * ```
 */
@Injectable({ providedIn: 'root' })
export class ChatTicketListService implements OnDestroy {
  private readonly calledService = inject(CalledService);
  private readonly chatRefresh = inject(ChatRefreshService);
  private readonly route = inject(ActivatedRoute);
  private readonly destroyRef = inject(DestroyRef);
  private readonly realtimeStore = inject(ChatRealtimeStore);

  /** Lista de tickets carregados. */
  readonly tickets = signal<Called[]>([]);

  /** Contadores de tickets por status. */
  readonly counts = signal<CalledCounts>({ all: 0, pending: 0, open: 0, closed: 0 });

  /** Indica se a lista está sendo carregada. */
  readonly loadingTickets = signal<boolean>(true);

  /** Termo de busca ativo. */
  readonly searchTerm = signal('');

  /** Overrides de status transitórios (ex: optimistic start). */
  private readonly transientStatusOverrides = new Map<string, CalledStatus>();
  private readonly transientStatusOverrideTimers = new Map<string, ReturnType<typeof setTimeout>>();

  constructor() {
    // Reload ticket list whenever ChatRefreshService triggers a refresh
    effect(() => {
      const tick = this.chatRefresh.refreshTick();
      if (tick > 0) {
        this.loadTickets();
      }
    });
  }

  ngOnDestroy(): void {
    for (const timer of this.transientStatusOverrideTimers.values()) {
      clearTimeout(timer);
    }
    this.transientStatusOverrideTimers.clear();
    this.transientStatusOverrides.clear();
  }

  /**
   * Carrega a lista de tickets da API, aplicando overrides transitórios.
   *
   * @param onComplete Callback opcional chamado ao finalizar (sucesso ou erro).
   */
  loadTickets(onComplete?: () => void): void {
    this.loadingTickets.set(true);
    const search = this.searchTerm().trim();

    this.calledService
      .list({ per_page: 30, ...(search ? { search } : {}) })
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (res) => {
          const items = this.applyTransientStatusOverrides(res.data);
          this.tickets.set(items);
          this.counts.set(res.counts ?? this.deriveCounts(res.data));
          this.syncTicketsToStore(items);
          this.loadingTickets.set(false);
          onComplete?.();
        },
        error: () => {
          this.loadingTickets.set(false);
          onComplete?.();
        },
      });
  }

  /**
   * Atualiza o termo de busca e recarrega a lista.
   *
   * @param term Novo termo de busca.
   */
  setSearchTerm(term: string): void {
    this.searchTerm.set(term);
    this.loadTickets();
  }

  /**
   * Busca os detalhes completos de um ticket via API e mescla no array local.
   * Garante que dados como contato, instância e mensagens estejam disponíveis.
   *
   * @param ticketId ID do ticket a hidratar.
   */
  hydrateTicket(ticketId: string): void {
    this.calledService
      .get(ticketId)
      .pipe(takeUntilDestroyed(this.destroyRef))
      .subscribe({
        next: (response) => {
          const fullTicket = response.data;

          this.tickets.update((items) => {
            const index = items.findIndex((item) => String(item.id) === ticketId);
            if (index < 0) {
              return [fullTicket, ...items];
            }

            const next = [...items];
            next[index] = {
              ...next[index],
              ...fullTicket,
            };
            return next;
          });

          // Sync hydrated ticket to realtime store
          this.syncTicketsToStore([fullTicket]);
        },
        error: () => {
          // keep current list state when detail fetch fails
        },
      });
  }

  /**
   * Syncs tickets to the realtime store for unified cache.
   * Merges into the store's ticket Map so realtime events have full data.
   */
  private syncTicketsToStore(items: Called[]): void {
    this.realtimeStore.tickets.update((map) => {
      const next = new Map(map);
      for (const ticket of items) {
        next.set(String(ticket.id), ticket);
      }
      return next;
    });
  }

  /**
   * Deriva contadores locais a partir da lista de tickets.
   * Utilizado como fallback quando a API não devolve `counts`.
   *
   * @param items Lista de tickets para contar.
   */
  deriveCounts(items: Called[]): CalledCounts {
    const next: CalledCounts = {
      all: items.length,
      pending: 0,
      open: 0,
      closed: 0,
      in_progress: 0,
    };

    for (const ticket of items) {
      if (ticket.status === 'pending') {
        next.pending += 1;
      } else if (ticket.status === 'open') {
        next.open += 1;
      } else if (ticket.status === 'closed') {
        next.closed += 1;
      } else if (ticket.status === 'in_progress') {
        next.in_progress = (next.in_progress ?? 0) + 1;
        next.open += 1;
      }
    }

    return next;
  }

  /**
   * Aplica um override de status transitório a um ticket (ex: optimistic update).
   * O override é removido automaticamente após ttlMs.
   *
   * @param id ID do ticket.
   * @param status Status temporário a aplicar.
   * @param ttlMs Tempo de vida do override em ms (default 5000).
   */
  setTransientStatusOverride(id: string, status: CalledStatus, ttlMs = 5000): void {
    this.transientStatusOverrides.set(id, status);

    const activeTimer = this.transientStatusOverrideTimers.get(id);
    if (activeTimer !== undefined) {
      clearTimeout(activeTimer);
    }

    const timer = setTimeout(() => {
      this.transientStatusOverrides.delete(id);
      this.transientStatusOverrideTimers.delete(id);
    }, ttlMs);

    this.transientStatusOverrideTimers.set(id, timer);
  }

  /**
   * Aplica overrides de status transitórios a uma lista de tickets.
   *
   * @param items Lista de tickets.
   */
  applyTransientStatusOverrides(items: Called[]): Called[] {
    if (this.transientStatusOverrides.size === 0) {
      return items;
    }

    return items.map((item) => {
      const overrideStatus = this.transientStatusOverrides.get(String(item.id));
      if (!overrideStatus) {
        return item;
      }

      return {
        ...item,
        status: overrideStatus,
      };
    });
  }

  /**
   * Sincroniza o ticket selecionado a partir da rota ativa (`/chat/:calledId`).
   * Se o ticket não existe na lista local, busca da API via hydrateTicket.
   *
   * @param selectedTicketId Signal do ticket selecionado (gerenciado pelo ChatPage/ChatStore).
   * @param onTicketNotFound Callback opcional quando ticket não existe localmente.
   */
  syncSelectedTicketFromRoute(
    selectedTicketId: { set(value: string | null): void },
    onTicketNotFound?: (ticketId: string) => void,
  ): void {
    let current = this.route.snapshot;
    while (current.firstChild) {
      current = current.firstChild;
    }
    const calledId = current.paramMap.get('calledId');
    selectedTicketId.set(calledId);

    if (calledId !== null && calledId !== '') {
      const exists = this.tickets().some((t) => String(t.id) === calledId);
      if (!exists) {
        this.hydrateTicket(calledId);
        onTicketNotFound?.(calledId);
      }
    }
  }
}
