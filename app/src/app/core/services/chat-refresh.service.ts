import { Injectable, signal } from '@angular/core';

/**
 * Serviço de coordenação para refresh da sidebar de chat.
 *
 * Suporta dois modos de atualização:
 * - `request()` → incrementa refreshTick para reload completo
 * - `markAsRead(ticketId)` → sinaliza atualização local sem recarregar lista
 */
@Injectable({ providedIn: 'root' })
export class ChatRefreshService {
  private readonly refreshSignal = signal(0);
  private readonly readTicketSignal = signal<string | null>(null);

  /** Signal para reload completo da sidebar. */
  readonly refreshTick = this.refreshSignal.asReadonly();

  /** Signal com ID do ticket que foi marcado como lido. */
  readonly readTicketId = this.readTicketSignal.asReadonly();

  /** Solicitar reload completo da sidebar (debounced pelo consumer). */
  request(): void {
    this.refreshSignal.update((value) => value + 1);
  }

  /** Sinalizar que um ticket foi marcado como lido (atualização local). */
  markAsRead(ticketId: string): void {
    this.readTicketSignal.set(ticketId);
  }
}
