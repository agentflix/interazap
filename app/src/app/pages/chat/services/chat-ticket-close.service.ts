import {
  type DestroyRef,
  Injectable,
  type Signal,
  type WritableSignal,
  inject,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { Router } from '@angular/router';
import { toast } from 'ngx-sonner';
import {
  type Called,
  type CalledCounts,
  CalledService,
} from 'src/app/core/services/called.service';
import { ChatTicketListService } from './chat-ticket-list.service';

/**
 * Encapsulates the close-ticket flow: confirm modal state, optimistic
 * update of tickets/counts, rollback on error and post-close navigation.
 *
 * Extracted from `Chat` host (FEAT-049) to keep the host thin.
 */
@Injectable({ providedIn: 'root' })
export class ChatTicketCloseService {
  private readonly calledService = inject(CalledService);
  private readonly ticketList = inject(ChatTicketListService);
  private readonly router = inject(Router);

  private readonly _isClosing = signal(false);
  private readonly _isConfirmOpen = signal(false);

  readonly isClosing: Signal<boolean> = this._isClosing.asReadonly();
  readonly isConfirmOpen: Signal<boolean> = this._isConfirmOpen.asReadonly();

  /** Opens the close-confirm modal if ticket is open. */
  openConfirm(ticket: Called | null): void {
    if (!ticket || ticket.status === 'closed' || this._isClosing()) return;
    this._isConfirmOpen.set(true);
  }

  /** Dismisses the close-confirm modal. */
  closeConfirm(): void {
    this._isConfirmOpen.set(false);
  }

  /**
   * Optimistically closes the ticket. Mutates the provided signals;
   * rolls back on API error.
   */
  confirm(
    ticket: Called | null,
    destroyRef: DestroyRef,
    ticketsSignal: WritableSignal<Called[]>,
    countsSignal: WritableSignal<CalledCounts>,
    selectedTicketIdSignal: WritableSignal<string | null>,
  ): void {
    if (!ticket || ticket.status === 'closed' || this._isClosing()) return;

    const ticketId = String(ticket.id);
    const previousTicket = { ...ticket };
    const previousCounts = countsSignal();

    this._isConfirmOpen.set(false);
    this._isClosing.set(true);

    ticketsSignal.update((items) =>
      items.map((item) =>
        String(item.id) === ticketId
          ? {
              ...item,
              status: 'closed',
              closed_at: item.closed_at ?? new Date().toISOString(),
            }
          : item,
      ),
    );

    countsSignal.update((current) => ({
      ...current,
      open: Math.max(0, current.open - 1),
      in_progress: Math.max(0, (current.in_progress ?? 0) - 1),
      closed: current.closed + 1,
    }));

    this.calledService
      .close(ticketId)
      .pipe(takeUntilDestroyed(destroyRef))
      .subscribe({
        next: (response) => {
          const closed = response.data;

          ticketsSignal.update((items) =>
            items.map((item) =>
              String(item.id) === ticketId
                ? {
                    ...item,
                    ...closed,
                    status: 'closed',
                    closed_at: closed.closed_at ?? item.closed_at,
                    updated_at: closed.updated_at ?? item.updated_at,
                  }
                : item,
            ),
          );

          this._isClosing.set(false);
          this.ticketList.loadTickets();
          toast.success('Chamado fechado com sucesso.');
          selectedTicketIdSignal.set(null);
          void this.router.navigate(['/chat']);
        },
        error: () => {
          ticketsSignal.update((items) =>
            items.map((item) => (String(item.id) === ticketId ? previousTicket : item)),
          );
          countsSignal.set(previousCounts);
          this._isClosing.set(false);
        },
      });
  }
}
