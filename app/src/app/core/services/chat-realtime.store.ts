import { computed, Injectable, signal } from '@angular/core';
import { type ChatRealtimeEvent, type ChatTypingEvent } from './chat-realtime.events';

/**
 * Store em memória para eventos de chat em tempo real.
 *
 * @remarks
 * Mantém um buffer rotativo de até 500 eventos, indexados por ticket para consulta rápida.
 * Também rastreia indicadores de digitação por ticket.
 */
const MAX_EVENT_HISTORY = 500;

@Injectable({ providedIn: 'root' })
export class ChatRealtimeStore {
  private readonly _events = signal<ChatRealtimeEvent[]>([]);
  private readonly _lastByTicket = signal<Map<string, ChatRealtimeEvent>>(new Map());
  private readonly _typingByTicket = signal<Map<string, ChatTypingEvent>>(new Map());

  readonly events = this._events.asReadonly();
  readonly lastEvent = computed(() => this._events().at(-1) ?? null);
  readonly lastByTicket = computed(() => this._lastByTicket());
  readonly typingByTicket = computed(() => this._typingByTicket());

  /**
   * Adiciona um evento ao store e atualiza os caches indexados por ticket.
   *
   * @param event - Evento em tempo real a registrar
   */
  push(event: ChatRealtimeEvent): void {
    this._events.update((current) => {
      if (current.length >= MAX_EVENT_HISTORY) {
        const [, ...tail] = current;
        return [...tail, event];
      }
      return [...current, event];
    });

    const ticketId = this.resolveTicketId(event);
    if (ticketId !== null) {
      const updated = new Map(this._lastByTicket());
      if (updated.has(ticketId)) {
        updated.delete(ticketId);
      }
      updated.set(ticketId, event);
      if (updated.size > MAX_EVENT_HISTORY) {
        const oldestKey = updated.keys().next().value;
        if (typeof oldestKey === 'string') {
          updated.delete(oldestKey);
        }
      }
      this._lastByTicket.set(updated);
    }

    if (event.type === 'typing') {
      this.updateTyping(event.payload);
    }
  }

  /**
   * Limpa todos os eventos e indicadores de digitação do store.
   */
  clear(): void {
    this._events.set([]);
    this._lastByTicket.set(new Map());
    this._typingByTicket.set(new Map());
  }

  /**
   * Define ou remove o indicador de digitação de um ticket específico.
   *
   * @param event - Evento de digitação contendo `ticket_id` e flag `is_typing`
   */
  setTyping(event: ChatTypingEvent): void {
    this.updateTyping(event);
  }

  private updateTyping(event: ChatTypingEvent): void {
    const map = new Map(this._typingByTicket());
    if (event.is_typing) {
      map.set(event.ticket_id, event);
    } else {
      map.delete(event.ticket_id);
    }
    this._typingByTicket.set(map);
  }

  private resolveTicketId(event: ChatRealtimeEvent): string | null {
    const ticketId = event.payload.ticket_id;
    if (typeof ticketId !== 'string') {
      return null;
    }

    const normalized = ticketId.trim();
    return normalized === '' ? null : normalized;
  }
}
