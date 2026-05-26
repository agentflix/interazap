import {
  type DestroyRef,
  Injectable,
  type Signal,
  type WritableSignal,
  inject,
  signal,
} from '@angular/core';
import { takeUntilDestroyed } from '@angular/core/rxjs-interop';
import { switchMap } from 'rxjs/operators';
import { type Called, CalledService } from 'src/app/core/services/called.service';
import { ChatTicketListService } from './chat-ticket-list.service';

/**
 * Encapsula o fluxo de transferência de ticket: estado do modal, carregamento,
 * mensagem de erro e o pipeline de confirmação (transferToUser → get → atualiza lista).
 *
 * Extraído do host `Chat` (FEAT-049) para manter o host enxuto.
 */
@Injectable({ providedIn: 'root' })
export class ChatTicketTransferService {
  private readonly calledService = inject(CalledService);
  private readonly ticketList = inject(ChatTicketListService);

  private readonly _isModalOpen = signal(false);
  private readonly _isLoading = signal(false);
  private readonly _error = signal<string | null>(null);

  readonly isModalOpen: Signal<boolean> = this._isModalOpen.asReadonly();
  readonly isLoading: Signal<boolean> = this._isLoading.asReadonly();
  readonly error: Signal<string | null> = this._error.asReadonly();

  /** Abre o modal de transferência se o ticket for transferível. */
  openModal(ticket: Called | null): void {
    if (!ticket || ticket.status === 'closed' || this._isLoading()) {
      return;
    }
    this._error.set(null);
    this._isModalOpen.set(true);
  }

  /** Closes transfer modal (no-op while loading). */
  closeModal(): void {
    if (this._isLoading()) return;
    this._error.set(null);
    this._isModalOpen.set(false);
  }

  /**
   * Confirms the transfer. Subscriptions are bound to the caller's DestroyRef.
   * Pipeline: transferToUser → get(ticketId) → patch ticket list.
   */
  confirm(
    event: { ticketId: string; toUserId: string; reason: string },
    destroyRef: DestroyRef,
    ticketsSignal: WritableSignal<Called[]>,
  ): void {
    this._error.set(null);
    this._isLoading.set(true);

    this.calledService
      .transferToUser(event.ticketId, { to_user_id: event.toUserId, reason: event.reason })
      .pipe(
        switchMap(() => this.calledService.get(event.ticketId)),
        takeUntilDestroyed(destroyRef),
      )
      .subscribe({
        next: (ticketResponse) => {
          const updated = ticketResponse.data;
          ticketsSignal.update((items) =>
            items.map((item) =>
              String(item.id) === event.ticketId ? { ...item, ...updated } : item,
            ),
          );

          this._isLoading.set(false);
          this._error.set(null);
          this._isModalOpen.set(false);
          this.ticketList.loadTickets();
        },
        error: () => {
          this._isLoading.set(false);
          this._error.set('Não foi possível transferir o chamado. Tente novamente.');
        },
      });
  }
}
